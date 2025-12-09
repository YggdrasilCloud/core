<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Tus;

use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderCommand;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Service\FileValidator;
use finfo;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\Events\TusEvent;
use TusPhp\Events\UploadComplete;

use function fclose;
use function fopen;
use function unlink;

/**
 * Handles the completion of a Tus upload.
 *
 * When a chunked upload finishes, this subscriber:
 * 1. Validates the completed file (MIME type, size)
 * 2. Dispatches the UploadPhotoToFolderCommand to process it
 * 3. Cleans up the temporary file
 */
final readonly class TusUploadCompleteSubscriber
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private FileValidator $fileValidator,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(TusEvent $event): void
    {
        if (!$event instanceof UploadComplete) {
            return;
        }

        $file = $event->getFile();
        $filePath = $file->getFilePath();

        // Extract metadata sent by the client
        $metadata = $file->details()['metadata'] ?? [];
        $folderId = $metadata['folderId'] ?? null;
        $ownerId = $metadata['ownerId'] ?? null;
        $fileName = $metadata['filename'] ?? $metadata['fileName'] ?? 'unnamed';
        $mimeType = $metadata['filetype'] ?? $metadata['mimeType'] ?? null;

        if ($folderId === null || $ownerId === null) {
            $this->logger->error('Tus upload missing required metadata', [
                'uploadKey' => $file->getKey(),
                'metadata' => $metadata,
            ]);

            return;
        }

        // Detect MIME type if not provided
        if ($mimeType === null) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($filePath) ?: 'application/octet-stream';
        }

        // Sanitize filename
        $sanitizedFileName = $this->fileValidator->sanitizeFilename($fileName);

        $this->logger->info('Processing completed Tus upload', [
            'uploadKey' => $file->getKey(),
            'folderId' => $folderId,
            'fileName' => $sanitizedFileName,
            'size' => $file->getFileSize(),
        ]);

        try {
            $photoId = PhotoId::generate();

            $fileStream = fopen($filePath, 'r');
            if ($fileStream === false) {
                $this->logger->error('Failed to open completed upload file', [
                    'filePath' => $filePath,
                ]);

                return;
            }

            try {
                $this->commandBus->dispatch(new UploadPhotoToFolderCommand(
                    $photoId->toString(),
                    $folderId,
                    $ownerId,
                    $sanitizedFileName,
                    $fileStream,
                    $mimeType,
                    $file->getFileSize(),
                ));
            } finally {
                fclose($fileStream);
            }

            $this->logger->info('Photo created from Tus upload', [
                'photoId' => $photoId->toString(),
                'folderId' => $folderId,
            ]);
        } finally {
            // Clean up the temporary file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}
