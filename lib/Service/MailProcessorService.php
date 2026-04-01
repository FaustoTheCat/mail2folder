<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Service;

use OCA\Mail2Folder\Db\ProcessedMail;
use OCA\Mail2Folder\Db\ProcessedMailMapper;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MailProcessorService
{
    private const APP_ID = 'mail2folder';
    private const DEFAULT_TARGET_FOLDER = 'Mail-Attachments';

    private ImapService $imapService;
    private AttachmentService $attachmentService;
    private ProcessedMailMapper $processedMailMapper;
    private IConfig $config;
    private IUserManager $userManager;
    private LoggerInterface $logger;

    public function __construct(
        ImapService $imapService,
        AttachmentService $attachmentService,
        ProcessedMailMapper $processedMailMapper,
        IConfig $config,
        IUserManager $userManager,
        LoggerInterface $logger
    ) {
        $this->imapService = $imapService;
        $this->attachmentService = $attachmentService;
        $this->processedMailMapper = $processedMailMapper;
        $this->config = $config;
        $this->userManager = $userManager;
        $this->logger = $logger;
    }

    /**
     * Main processing loop: connect, fetch, route, store.
     *
     * @return array Summary of processing results
     */
    public function processNewMails(): array
    {
        $summary = [
            'processed' => 0,
            'attachments_saved' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => [],
        ];

        // Load admin IMAP settings
        $host = $this->config->getAppValue(self::APP_ID, 'imap_host', '');
        $port = (int)$this->config->getAppValue(self::APP_ID, 'imap_port', '993');
        $username = $this->config->getAppValue(self::APP_ID, 'imap_username', '');
        $password = $this->config->getAppValue(self::APP_ID, 'imap_password', '');
        $encryption = $this->config->getAppValue(self::APP_ID, 'imap_encryption', 'ssl');
        $folder = $this->config->getAppValue(self::APP_ID, 'imap_folder', 'INBOX');
        $deleteAfter = $this->config->getAppValue(self::APP_ID, 'delete_after_processing', 'no') === 'yes';
        $subfolderMode = $this->config->getAppValue(self::APP_ID, 'subfolder_mode', 'date');
        $fallbackUser = $this->config->getAppValue(self::APP_ID, 'fallback_user', '');
        $subjectPattern = $this->config->getAppValue(self::APP_ID, 'subject_pattern', '[{username}]');
        $saveBody = $this->config->getAppValue(self::APP_ID, 'save_body', 'no') === 'yes';

        if (empty($host) || empty($username) || empty($password)) {
            $this->logger->warning('Mail2Folder: IMAP settings incomplete, skipping.');
            return $summary;
        }

        try {
            $this->imapService->connect($host, $port, $username, $password, $encryption, $folder);
        } catch (\RuntimeException $e) {
            $this->logger->error('Mail2Folder: IMAP connection failed', ['error' => $e->getMessage()]);
            $summary['errors']++;
            $summary['details'][] = 'Connection failed: ' . $e->getMessage();
            return $summary;
        }

        try {
            $messages = $this->imapService->fetchUnreadMessages(50);

            foreach ($messages as $msg) {
                $result = $this->processMessage($msg, $deleteAfter, $subfolderMode, $fallbackUser, $subjectPattern, $saveBody);
                $summary['processed']++;
                $summary['attachments_saved'] += $result['attachments'];

                if ($result['status'] === 'skipped') {
                    $summary['skipped']++;
                } elseif ($result['status'] === 'error') {
                    $summary['errors']++;
                }

                $summary['details'][] = $result;
            }
        } catch (\Exception $e) {
            $this->logger->error('Mail2Folder: Processing error', ['error' => $e->getMessage()]);
            $summary['errors']++;
        } finally {
            if ($deleteAfter) {
                $this->imapService->expunge();
            }
            $this->imapService->disconnect();
        }

        $this->logger->info('Mail2Folder: Processing complete', [
            'processed' => $summary['processed'],
            'attachments' => $summary['attachments_saved'],
            'errors' => $summary['errors'],
        ]);

        return $summary;
    }

    /**
     * Process a single email message.
     */
    private function processMessage(
        array $msg,
        bool $deleteAfter,
        string $subfolderMode,
        string $fallbackUser,
        string $subjectPattern,
        bool $saveBody
    ): array {
        $result = [
            'uid' => $msg['uid'],
            'from' => $msg['from'],
            'subject' => $msg['subject'],
            'status' => 'success',
            'attachments' => 0,
            'error' => null,
        ];

        // Check if already processed
        if ($this->processedMailMapper->isMessageIdProcessed($msg['messageId'])) {
            $this->imapService->markAsSeen($msg['msgNum']);
            $result['status'] = 'skipped';
            return $result;
        }

        // Route: determine target user from subject line
        $userId = $this->resolveUserFromSubject($msg['subject'], $subjectPattern);

        // If no user found in subject, try fallback user
        if ($userId === null && !empty($fallbackUser)) {
            if ($this->userManager->userExists($fallbackUser)) {
                $userId = $fallbackUser;
                $this->logger->debug('Mail2Folder: Using fallback user', [
                    'user' => $fallbackUser,
                    'subject' => $msg['subject'],
                ]);
            }
        }

        if ($userId === null) {
            $this->logger->info('Mail2Folder: No matching user found in subject', [
                'subject' => $msg['subject'],
                'from' => $msg['from'],
            ]);
            // Don't mark as seen — leave for retry or manual handling
            $result['status'] = 'skipped';
            $result['error'] = 'No matching Nextcloud user in subject';
            $this->recordProcessed($msg, '', 0, 'skipped', $result['error']);
            return $result;
        }

        // Get user's target folder
        $targetFolder = $this->config->getUserValue(
            $userId,
            self::APP_ID,
            'target_folder',
            self::DEFAULT_TARGET_FOLDER
        );

        // Get attachments
        try {
            $attachments = $this->imapService->getAttachments($msg['msgNum']);
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['error'] = 'Failed to extract attachments: ' . $e->getMessage();
            $this->recordProcessed($msg, $userId, 0, 'error', $result['error']);
            $this->imapService->markAsSeen($msg['msgNum']);
            return $result;
        }

        // Determine subfolder
        $subFolder = $this->buildSubFolder($subfolderMode, $msg);

        if (empty($attachments) && !$saveBody) {
            // No attachments and body saving disabled — nothing to do
            $this->imapService->markAsSeen($msg['msgNum']);
            $result['status'] = 'success';
            $this->recordProcessed($msg, $userId, 0, 'success');
            if ($deleteAfter) {
                $this->imapService->deleteMessage($msg['msgNum']);
            }
            return $result;
        }

        // Save mail body as .txt file if enabled
        if ($saveBody) {
            try {
                $body = $this->imapService->getBody($msg['msgNum']);
                if (!empty(trim($body))) {
                    // Build header block for the text file
                    $header = "Von: " . $msg['from'] . "\n"
                        . "Betreff: " . $msg['subject'] . "\n"
                        . "Datum: " . ($msg['date'] ?? date('Y-m-d H:i:s')) . "\n"
                        . str_repeat('-', 60) . "\n\n";

                    $bodyFilename = $this->sanitizeFilenameFromSubject($msg['subject']) . '.txt';

                    $this->attachmentService->saveAttachment(
                        $userId,
                        $targetFolder,
                        $bodyFilename,
                        $header . $body,
                        $subFolder
                    );
                }
            } catch (\Exception $e) {
                $this->logger->warning('Mail2Folder: Failed to save mail body', [
                    'user' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($attachments)) {
            // Body was saved (if enabled), but no attachments
            $this->imapService->markAsSeen($msg['msgNum']);
            $result['status'] = 'success';
            $this->recordProcessed($msg, $userId, 0, 'success');
            if ($deleteAfter) {
                $this->imapService->deleteMessage($msg['msgNum']);
            }
            return $result;
        }

        // Save each attachment
        $savedCount = 0;
        foreach ($attachments as $attachment) {
            try {
                $this->attachmentService->saveAttachment(
                    $userId,
                    $targetFolder,
                    $attachment['filename'],
                    $attachment['content'],
                    $subFolder
                );
                $savedCount++;
            } catch (\Exception $e) {
                $this->logger->error('Mail2Folder: Failed to save attachment', [
                    'user' => $userId,
                    'filename' => $attachment['filename'],
                    'error' => $e->getMessage(),
                ]);
                $result['error'] = 'Some attachments failed: ' . $e->getMessage();
            }
        }

        $result['attachments'] = $savedCount;

        // Mark as read / delete
        $this->imapService->markAsSeen($msg['msgNum']);
        if ($deleteAfter) {
            $this->imapService->deleteMessage($msg['msgNum']);
        }

        // Record
        $status = $savedCount === count($attachments) ? 'success' : 'partial';
        $this->recordProcessed($msg, $userId, $savedCount, $status, $result['error']);

        return $result;
    }

    /**
     * Resolve a Nextcloud user ID from the email subject line.
     *
     * Supports patterns:
     * - [{username}]   — looks for [anna] in the subject
     * - @{username}     — looks for @anna in the subject
     * - user:{username} — looks for user:anna in the subject
     */
    private function resolveUserFromSubject(string $subject, string $pattern): ?string
    {
        $matches = [];

        // Pattern: [{username}] → extract from square brackets
        if (str_contains($pattern, '[{username}]')) {
            if (preg_match_all('/\[([a-zA-Z0-9._@-]+)\]/', $subject, $matches)) {
                foreach ($matches[1] as $candidate) {
                    $candidate = strtolower(trim($candidate));
                    if ($this->userManager->userExists($candidate)) {
                        return $candidate;
                    }
                    $found = $this->findUserCaseInsensitive($candidate);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        // Pattern: @{username} → extract @mention style
        if (str_contains($pattern, '@{username}')) {
            if (preg_match_all('/@([a-zA-Z0-9._-]+)/', $subject, $matches)) {
                foreach ($matches[1] as $candidate) {
                    $candidate = strtolower(trim($candidate));
                    if ($this->userManager->userExists($candidate)) {
                        return $candidate;
                    }
                    $found = $this->findUserCaseInsensitive($candidate);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        // Pattern: user:{username} → extract "user:name" style
        if (str_contains($pattern, 'user:{username}')) {
            if (preg_match('/user:([a-zA-Z0-9._@-]+)/i', $subject, $matches)) {
                $candidate = strtolower(trim($matches[1]));
                if ($this->userManager->userExists($candidate)) {
                    return $candidate;
                }
                $found = $this->findUserCaseInsensitive($candidate);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Find a user by case-insensitive UID search.
     */
    private function findUserCaseInsensitive(string $searchUid): ?string
    {
        $users = $this->userManager->search($searchUid, 5);
        foreach ($users as $user) {
            if (strtolower($user->getUID()) === $searchUid) {
                return $user->getUID();
            }
        }
        return null;
    }

    /**
     * Build a subfolder path for organizing attachments.
     */
    private function buildSubFolder(string $mode, array $msg): string
    {
        return match ($mode) {
            'date' => date('Y/Y-m-d'),
            'month' => date('Y/m'),
            'sender' => $this->sanitizeFolderName($msg['from']),
            'subject' => $this->sanitizeFolderName(
                $this->cleanSubjectForFolder($msg['subject'])
            ),
            'flat' => '',
            default => date('Y/Y-m-d'),
        };
    }

    /**
     * Remove the [username] tag from the subject for folder naming.
     */
    private function cleanSubjectForFolder(string $subject): string
    {
        $clean = preg_replace('/\[[a-zA-Z0-9._@-]+\]\s*/', '', $subject);
        $clean = preg_replace('/@[a-zA-Z0-9._-]+\s*/', '', $clean);
        $clean = preg_replace('/user:[a-zA-Z0-9._@-]+\s*/i', '', $clean);

        $clean = trim($clean);
        return !empty($clean) ? $clean : 'no-subject';
    }

    /**
     * Sanitize a string for use as a folder name.
     */
    private function sanitizeFolderName(string $name): string
    {
        if (str_contains($name, '@')) {
            $name = substr($name, 0, strpos($name, '@'));
        }

        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name, '. ');

        if (empty($name)) {
            $name = 'unknown';
        }

        return substr($name, 0, 100);
    }

    /**
     * Create a sanitized filename from an email subject.
     * Removes user tags ([anna], @anna, user:anna) and cleans special characters.
     */
    private function sanitizeFilenameFromSubject(string $subject): string
    {
        // Remove user tags
        $name = $this->cleanSubjectForFolder($subject);

        // Remove Re:/Fwd:/AW:/WG: prefixes
        $name = preg_replace('/^(Re|Fwd|AW|WG)\s*:\s*/i', '', $name);

        // Replace problematic filename characters
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name, '. _');

        if (empty($name)) {
            $name = 'mail_' . date('Y-m-d_His');
        }

        // Limit length (leave room for .txt extension)
        return substr($name, 0, 150);
    }

    /**
     * Record a processed mail in the database.
     */
    private function recordProcessed(
        array $msg,
        string $userId,
        int $attachmentCount,
        string $status,
        ?string $error = null
    ): void {
        $record = new ProcessedMail();
        $record->setMessageId($msg['messageId']);
        $record->setUid($msg['uid']);
        $record->setUserId($userId);
        $record->setSender($msg['from']);
        $record->setSubject(substr($msg['subject'] ?? '', 0, 500));
        $record->setAttachmentCount($attachmentCount);
        $record->setProcessedAt(date('Y-m-d H:i:s'));
        $record->setStatus($status);
        $record->setErrorMessage($error);

        try {
            $this->processedMailMapper->insert($record);
        } catch (\Exception $e) {
            $this->logger->error('Mail2Folder: Failed to record processed mail', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
