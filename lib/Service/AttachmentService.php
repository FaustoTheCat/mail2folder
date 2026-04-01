<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Service;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

class AttachmentService
{
    private IRootFolder $rootFolder;
    private LoggerInterface $logger;

    public function __construct(
        IRootFolder $rootFolder,
        LoggerInterface $logger
    ) {
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    /**
     * Save an attachment to a user's Nextcloud folder.
     *
     * @param string $userId       The Nextcloud user ID
     * @param string $targetFolder Relative path within user's files (e.g. "Mail-Attachments")
     * @param string $filename     The attachment filename
     * @param string $content      The file content (binary)
     * @param string $subFolder    Optional subfolder (e.g. date-based or sender-based)
     *
     * @return string The final path where the file was stored
     *
     * @throws NotPermittedException
     */
    public function saveAttachment(
        string $userId,
        string $targetFolder,
        string $filename,
        string $content,
        string $subFolder = ''
    ): string {
        $userFolder = $this->rootFolder->getUserFolder($userId);

        // Build full target path
        $folderPath = $targetFolder;
        if (!empty($subFolder)) {
            $folderPath .= '/' . $subFolder;
        }

        // Ensure target directory exists
        $folder = $this->ensureFolderExists($userFolder, $folderPath);

        // Handle filename conflicts (auto-rename)
        $finalFilename = $this->resolveConflict($folder, $filename);

        // Create the file
        $file = $folder->newFile($finalFilename);
        $file->putContent($content);

        $finalPath = $folderPath . '/' . $finalFilename;

        $this->logger->info('Mail2Folder: Saved attachment', [
            'user' => $userId,
            'path' => $finalPath,
            'size' => strlen($content),
        ]);

        return $finalPath;
    }

    /**
     * Ensure a folder path exists, creating intermediate folders as needed.
     *
     * @return \OCP\Files\Folder
     */
    private function ensureFolderExists(\OCP\Files\Folder $baseFolder, string $path): \OCP\Files\Folder
    {
        $parts = explode('/', trim($path, '/'));
        $current = $baseFolder;

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            try {
                $node = $current->get($part);
                if ($node instanceof \OCP\Files\Folder) {
                    $current = $node;
                } else {
                    // A file exists with the folder name — append suffix
                    $current = $current->newFolder($part . '_folder');
                }
            } catch (NotFoundException $e) {
                $current = $current->newFolder($part);
            }
        }

        return $current;
    }

    /**
     * Resolve filename conflicts by appending a counter.
     * photo.jpg → photo.jpg, photo (2).jpg, photo (3).jpg, ...
     */
    private function resolveConflict(\OCP\Files\Folder $folder, string $filename): string
    {
        if (!$folder->nodeExists($filename)) {
            return $filename;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $counter = 2;

        do {
            $newName = $ext !== ''
                ? sprintf('%s (%d).%s', $base, $counter, $ext)
                : sprintf('%s (%d)', $base, $counter);
            $counter++;
        } while ($folder->nodeExists($newName) && $counter < 1000);

        return $newName;
    }
}
