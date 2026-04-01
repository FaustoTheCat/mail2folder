<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Service;

use Psr\Log\LoggerInterface;

class ImapService
{
    private LoggerInterface $logger;

    /** @var \IMAP\Connection|null */
    private $connection = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Connect to the IMAP server.
     *
     * @throws \RuntimeException if connection fails
     */
    public function connect(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption = 'ssl',
        string $folder = 'INBOX'
    ): void {
        if (!function_exists('imap_open')) {
            throw new \RuntimeException(
                'PHP IMAP extension is not installed. '
                . 'Install it with: apt-get install php-imap'
            );
        }

        $flags = match ($encryption) {
            'ssl' => '/imap/ssl/validate-cert',
            'ssl-novalidate' => '/imap/ssl/novalidate-cert',
            'tls' => '/imap/tls',
            'starttls' => '/imap/tls/novalidate-cert',
            'none' => '/imap/notls',
            default => '/imap/ssl',
        };

        $mailbox = '{' . $host . ':' . $port . $flags . '}' . $folder;

        $this->logger->debug('Mail2Folder: Connecting to IMAP', [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'folder' => $folder,
        ]);

        $conn = @imap_open($mailbox, $username, $password);

        if ($conn === false) {
            $errors = imap_errors();
            $errorMsg = $errors ? implode('; ', $errors) : 'Unknown IMAP error';
            throw new \RuntimeException('IMAP connection failed: ' . $errorMsg);
        }

        $this->connection = $conn;
    }

    /**
     * Disconnect from IMAP server.
     */
    public function disconnect(): void
    {
        if ($this->connection !== null) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Test the IMAP connection. Returns true on success, error string on failure.
     */
    public function testConnection(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption = 'ssl'
    ): string|bool {
        try {
            $this->connect($host, $port, $username, $password, $encryption);
            $check = imap_check($this->connection);
            $this->disconnect();

            if ($check === false) {
                return 'Connected but could not read mailbox status.';
            }

            return true;
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Fetch unread messages from the mailbox.
     *
     * @return array Array of message data with keys: uid, messageId, from, to, subject, date
     */
    public function fetchUnreadMessages(int $limit = 50): array
    {
        $this->ensureConnected();

        $messageNums = imap_search($this->connection, 'UNSEEN');

        if ($messageNums === false) {
            return [];
        }

        // Limit the number of messages processed per run
        $messageNums = array_slice($messageNums, 0, $limit);
        $messages = [];

        foreach ($messageNums as $msgNum) {
            $uid = imap_uid($this->connection, $msgNum);
            $header = imap_headerinfo($this->connection, $msgNum);

            if ($header === false) {
                $this->logger->warning('Mail2Folder: Could not read header for message #' . $msgNum);
                continue;
            }

            $toAddresses = [];
            if (isset($header->to)) {
                foreach ($header->to as $to) {
                    $addr = $to->mailbox . '@' . ($to->host ?? '');
                    $toAddresses[] = strtolower($addr);
                }
            }

            // Also check CC for catch-all routing
            if (isset($header->cc)) {
                foreach ($header->cc as $cc) {
                    $addr = $cc->mailbox . '@' . ($cc->host ?? '');
                    $toAddresses[] = strtolower($addr);
                }
            }

            $fromAddress = '';
            if (isset($header->from[0])) {
                $from = $header->from[0];
                $fromAddress = $from->mailbox . '@' . ($from->host ?? '');
            }

            $messageId = $header->message_id ?? ('uid-' . $uid);
            // Clean up message ID
            $messageId = trim($messageId, '<> ');

            $messages[] = [
                'msgNum' => $msgNum,
                'uid' => $uid,
                'messageId' => $messageId,
                'from' => strtolower($fromAddress),
                'to' => $toAddresses,
                'subject' => $this->decodeMimeStr($header->subject ?? '(no subject)'),
                'date' => $header->date ?? '',
            ];
        }

        return $messages;
    }

    /**
     * Get attachments from a message.
     *
     * @return array Array of attachments with keys: filename, content, size, mime
     */
    public function getAttachments(int $msgNum): array
    {
        $this->ensureConnected();

        $structure = imap_fetchstructure($this->connection, $msgNum);
        if ($structure === false) {
            return [];
        }

        $attachments = [];
        $this->extractAttachments($msgNum, $structure, '', $attachments);

        return $attachments;
    }

    /**
     * Mark a message as seen/read.
     */
    public function markAsSeen(int $msgNum): void
    {
        $this->ensureConnected();
        imap_setflag_full($this->connection, (string)$msgNum, '\\Seen');
    }

    /**
     * Delete a message (move to trash).
     */
    public function deleteMessage(int $msgNum): void
    {
        $this->ensureConnected();
        imap_delete($this->connection, (string)$msgNum);
    }

    /**
     * Expunge deleted messages.
     */
    public function expunge(): void
    {
        $this->ensureConnected();
        imap_expunge($this->connection);
    }

    /**
     * Get the plain text body of a message.
     * Falls back to stripped HTML if no plain text part exists.
     */
    public function getBody(int $msgNum): string
    {
        $this->ensureConnected();

        $structure = imap_fetchstructure($this->connection, $msgNum);
        if ($structure === false) {
            return '';
        }

        // Simple (non-multipart) message
        if (empty($structure->parts)) {
            $body = imap_body($this->connection, $msgNum);
            if ($body === false) {
                return '';
            }
            $body = $this->decodeContent($body, $structure->encoding ?? 0);
            $body = $this->convertCharset($body, $structure);

            // If it's HTML, strip tags
            if (isset($structure->subtype) && strtoupper($structure->subtype) === 'HTML') {
                $body = $this->htmlToPlainText($body);
            }
            return $body;
        }

        // Multipart: look for text/plain first, then text/html
        $plainText = $this->findBodyPart($msgNum, $structure, 'PLAIN');
        if (!empty($plainText)) {
            return $plainText;
        }

        $htmlText = $this->findBodyPart($msgNum, $structure, 'HTML');
        if (!empty($htmlText)) {
            return $this->htmlToPlainText($htmlText);
        }

        return '';
    }

    /**
     * Find a specific text body part (PLAIN or HTML) in a multipart message.
     */
    private function findBodyPart(int $msgNum, object $structure, string $subtype, string $partNum = ''): string
    {
        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $currentPart = $partNum === '' ? (string)($index + 1) : $partNum . '.' . ($index + 1);

                // Check if this is the part we want
                if (($part->type ?? -1) === 0 && strtoupper($part->subtype ?? '') === $subtype) {
                    // Make sure it's not an attachment
                    $isAttachment = false;
                    if (isset($part->ifdisposition) && $part->ifdisposition) {
                        $isAttachment = strtolower($part->disposition) === 'attachment';
                    }

                    if (!$isAttachment) {
                        $body = imap_fetchbody($this->connection, $msgNum, $currentPart);
                        if ($body !== false) {
                            $body = $this->decodeContent($body, $part->encoding ?? 0);
                            $body = $this->convertCharset($body, $part);
                            return $body;
                        }
                    }
                }

                // Recurse into nested multipart
                if (isset($part->parts)) {
                    $result = $this->findBodyPart($msgNum, $part, $subtype, $currentPart);
                    if (!empty($result)) {
                        return $result;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Convert body text to UTF-8 based on the part's charset parameter.
     */
    private function convertCharset(string $text, object $structure): string
    {
        $charset = 'UTF-8';

        if (isset($structure->ifparameters) && $structure->ifparameters) {
            foreach ($structure->parameters as $param) {
                if (strtolower($param->attribute) === 'charset') {
                    $charset = strtoupper($param->value);
                    break;
                }
            }
        }

        if ($charset !== 'UTF-8' && $charset !== 'US-ASCII') {
            $converted = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $text;
    }

    /**
     * Convert HTML to readable plain text.
     */
    private function htmlToPlainText(string $html): string
    {
        // Replace common block elements with newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $text);
        $text = preg_replace('/<(hr)\s*\/?>/i', "\n---\n", $text);

        // Remove all remaining tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Recursively extract attachments from a message structure.
     */
    private function extractAttachments(int $msgNum, object $structure, string $partNum, array &$attachments): void
    {
        // Check if this part is an attachment
        $filename = $this->getAttachmentFilename($structure);

        if ($filename !== null && $partNum !== '') {
            $content = imap_fetchbody($this->connection, $msgNum, $partNum);

            if ($content !== false) {
                // Decode the content based on encoding
                $content = $this->decodeContent($content, $structure->encoding ?? 0);

                $attachments[] = [
                    'filename' => $this->sanitizeFilename($filename),
                    'content' => $content,
                    'size' => strlen($content),
                    'mime' => $this->getMimeType($structure),
                ];
            }
        }

        // Recurse into multipart messages
        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $subPartNum = $partNum === '' ? (string)($index + 1) : $partNum . '.' . ($index + 1);
                $this->extractAttachments($msgNum, $part, $subPartNum, $attachments);
            }
        }
    }

    /**
     * Get the filename of an attachment, if present.
     */
    private function getAttachmentFilename(object $structure): ?string
    {
        // Check disposition
        $isAttachment = false;
        $filename = null;

        if (isset($structure->ifdisposition) && $structure->ifdisposition) {
            $disposition = strtolower($structure->disposition);
            if ($disposition === 'attachment' || $disposition === 'inline') {
                $isAttachment = true;

                if (isset($structure->ifdparameters) && $structure->ifdparameters) {
                    foreach ($structure->dparameters as $param) {
                        if (strtolower($param->attribute) === 'filename') {
                            $filename = $this->decodeMimeStr($param->value);
                        }
                    }
                }
            }
        }

        // Also check parameters for filename (some mail clients use this)
        if ($filename === null && isset($structure->ifparameters) && $structure->ifparameters) {
            foreach ($structure->parameters as $param) {
                if (strtolower($param->attribute) === 'name') {
                    $filename = $this->decodeMimeStr($param->value);
                    $isAttachment = true;
                }
            }
        }

        // Skip inline text/html/plain parts that are message body
        if ($isAttachment && $filename !== null) {
            return $filename;
        }

        // Treat non-text parts without explicit disposition as attachments too
        if (!$isAttachment && isset($structure->type) && $structure->type > 0) {
            // type 0 = text, skip those without filename
            if (isset($structure->ifparameters) && $structure->ifparameters) {
                foreach ($structure->parameters as $param) {
                    if (strtolower($param->attribute) === 'name') {
                        return $this->decodeMimeStr($param->value);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Decode content based on IMAP encoding type.
     */
    private function decodeContent(string $content, int $encoding): string
    {
        return match ($encoding) {
            0 => $content,                        // 7BIT
            1 => $content,                        // 8BIT
            2 => $content,                        // BINARY
            3 => base64_decode($content),          // BASE64
            4 => quoted_printable_decode($content), // QUOTED-PRINTABLE
            default => $content,
        };
    }

    /**
     * Get MIME type from structure.
     */
    private function getMimeType(object $structure): string
    {
        $primaryTypes = [
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            7 => 'model',
            8 => 'other',
        ];

        $type = $primaryTypes[$structure->type ?? 3] ?? 'application';
        $subtype = strtolower($structure->subtype ?? 'octet-stream');

        return $type . '/' . $subtype;
    }

    /**
     * Decode MIME encoded strings (e.g. UTF-8 subjects, filenames).
     */
    private function decodeMimeStr(string $str): string
    {
        $elements = imap_mime_header_decode($str);
        $decoded = '';

        foreach ($elements as $element) {
            $charset = strtolower($element->charset);
            $text = $element->text;

            if ($charset !== 'default' && $charset !== 'us-ascii' && $charset !== 'utf-8') {
                $converted = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $text);
                $text = $converted !== false ? $converted : $text;
            }

            $decoded .= $text;
        }

        return $decoded;
    }

    /**
     * Sanitize a filename for safe storage.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove path components
        $filename = basename($filename);

        // Replace problematic characters
        $filename = preg_replace('/[\/\\\\:*?"<>|]/', '_', $filename);

        // Remove leading dots (hidden files)
        $filename = ltrim($filename, '.');

        // Ensure non-empty filename
        if (empty($filename)) {
            $filename = 'attachment_' . time();
        }

        // Limit length
        if (strlen($filename) > 200) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($base, 0, 190) . '.' . $ext;
        }

        return $filename;
    }

    private function ensureConnected(): void
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Not connected to IMAP server. Call connect() first.');
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
