<?php

declare(strict_types=1);

namespace App\Photo\Application\Service;

use App\File\Domain\Model\StoredObject;
use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Application\Port\ThumbnailServiceInterface;
use App\Photo\Domain\Model\PathInfo;
use App\Photo\Domain\Service\ThumbnailStrategy\ThumbnailGeneratorStrategyInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function bin2hex;
use function fclose;
use function file_exists;
use function filesize;
use function fopen;
use function is_dir;
use function mkdir;
use function random_bytes;
use function stream_copy_to_stream;
use function unlink;

/**
 * Application service for thumbnail generation.
 *
 * Orchestrates thumbnail generation across different storage adapters:
 * - For local storage: direct filesystem access
 * - For remote storage (S3): download → generate → upload
 *
 * Uses the Strategy pattern for extensibility (images, videos, PDFs, etc.)
 */
final readonly class ThumbnailService implements ThumbnailServiceInterface
{
    private const string LOCAL_ADAPTER = 'local';
    private const int DEFAULT_THUMBNAIL_WIDTH = 300;
    private const int DEFAULT_THUMBNAIL_HEIGHT = 300;

    /**
     * @param iterable<ThumbnailGeneratorStrategyInterface> $strategies
     */
    public function __construct(
        private iterable $strategies,
        private FileStorageInterface $fileStorage,
        private LoggerInterface $logger,
        private string $tempDir,
        private string $storagePath,
    ) {}

    /**
     * Generate a thumbnail for a stored file.
     *
     * @param StoredObject $storedObject The original file metadata
     * @param string       $mimeType     The MIME type of the original file
     *
     * @return null|string The storage key of the generated thumbnail, or null if generation failed
     */
    public function generateThumbnail(StoredObject $storedObject, string $mimeType): ?string
    {
        try {
            $strategy = $this->findStrategy($mimeType);

            if ($strategy === null) {
                $this->logger->debug('No thumbnail strategy found for MIME type', [
                    'mimeType' => $mimeType,
                    'key' => $storedObject->key,
                ]);

                return null;
            }

            if (!$strategy->isAvailable()) {
                $this->logger->warning('Thumbnail strategy not available (missing tools)', [
                    'strategy' => $strategy::class,
                    'mimeType' => $mimeType,
                    'key' => $storedObject->key,
                ]);

                return null;
            }

            if ($storedObject->adapter === self::LOCAL_ADAPTER) {
                return $this->generateForLocalStorage($storedObject, $strategy);
            }

            return $this->generateForRemoteStorage($storedObject, $strategy);
        } catch (RuntimeException $e) {
            $this->logger->warning('Thumbnail generation failed', [
                'key' => $storedObject->key,
                'adapter' => $storedObject->adapter,
                'mimeType' => $mimeType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function findStrategy(string $mimeType): ?ThumbnailGeneratorStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($mimeType)) {
                return $strategy;
            }
        }

        return null;
    }

    /**
     * Generate thumbnail for local storage (direct filesystem access).
     */
    private function generateForLocalStorage(
        StoredObject $storedObject,
        ThumbnailGeneratorStrategyInterface $strategy,
    ): string {
        $absolutePath = $this->storagePath.'/'.$storedObject->key;

        if (!file_exists($absolutePath)) {
            throw new RuntimeException('Source file not found: '.$absolutePath);
        }

        $thumbnailRelativePath = $this->buildThumbnailKey($storedObject->key);
        $thumbnailAbsolutePath = $this->storagePath.'/'.$thumbnailRelativePath;

        // Ensure thumbnail directory exists
        $thumbnailDir = dirname($thumbnailAbsolutePath);
        if (!is_dir($thumbnailDir) && !mkdir($thumbnailDir, 0755, true) && !is_dir($thumbnailDir)) {
            throw new RuntimeException('Failed to create thumbnail directory');
        }

        $strategy->generate(
            $absolutePath,
            $thumbnailAbsolutePath,
            self::DEFAULT_THUMBNAIL_WIDTH,
            self::DEFAULT_THUMBNAIL_HEIGHT,
        );

        $this->logger->info('Thumbnail generated for local storage', [
            'originalKey' => $storedObject->key,
            'thumbnailKey' => $thumbnailRelativePath,
        ]);

        return $thumbnailRelativePath;
    }

    /**
     * Generate thumbnail for remote storage (S3, MinIO, etc.).
     *
     * Flow:
     * 1. Download original from remote storage to temp file
     * 2. Generate thumbnail locally using the appropriate strategy
     * 3. Upload thumbnail to remote storage
     * 4. Clean up temp files
     */
    private function generateForRemoteStorage(
        StoredObject $storedObject,
        ThumbnailGeneratorStrategyInterface $strategy,
    ): string {
        $this->logger->debug('Generating thumbnail for remote storage', [
            'key' => $storedObject->key,
            'adapter' => $storedObject->adapter,
            'strategy' => $strategy::class,
        ]);

        // Ensure temp directory exists
        if (!is_dir($this->tempDir) && !mkdir($this->tempDir, 0755, true) && !is_dir($this->tempDir)) {
            throw new RuntimeException('Failed to create temp directory: '.$this->tempDir);
        }

        // Download original to temp file with appropriate extension
        $originalStream = $this->fileStorage->readStream($storedObject->key);
        $pathInfo = PathInfo::fromPath($storedObject->key);
        $extension = $pathInfo->getExtension() !== null ? '.'.$pathInfo->getExtension() : '.tmp';
        $tempOriginalPath = $this->tempDir.'/ygg_orig_'.bin2hex(random_bytes(16)).$extension;
        $tempThumbnailPath = $this->tempDir.'/ygg_thumb_'.bin2hex(random_bytes(16)).'.jpg';

        try {
            $tempFile = fopen($tempOriginalPath, 'w');
            if ($tempFile === false) {
                throw new RuntimeException('Failed to create temp file');
            }

            stream_copy_to_stream($originalStream, $tempFile);
            fclose($tempFile);
            fclose($originalStream);

            // Generate thumbnail using the strategy
            $strategy->generate(
                $tempOriginalPath,
                $tempThumbnailPath,
                self::DEFAULT_THUMBNAIL_WIDTH,
                self::DEFAULT_THUMBNAIL_HEIGHT,
            );

            // Build the thumbnail storage key
            $thumbnailKey = $this->buildThumbnailKey($storedObject->key);

            // Upload thumbnail to remote storage
            $thumbnailStream = fopen($tempThumbnailPath, 'r');
            if ($thumbnailStream === false) {
                throw new RuntimeException('Failed to open generated thumbnail');
            }

            try {
                $thumbnailSize = filesize($tempThumbnailPath);
                if ($thumbnailSize === false) {
                    $thumbnailSize = 0;
                }

                $this->fileStorage->save(
                    $thumbnailStream,
                    $thumbnailKey,
                    'image/jpeg',
                    $thumbnailSize
                );
            } finally {
                fclose($thumbnailStream);
            }

            $this->logger->info('Thumbnail uploaded to remote storage', [
                'originalKey' => $storedObject->key,
                'thumbnailKey' => $thumbnailKey,
            ]);

            return $thumbnailKey;
        } finally {
            // Clean up temp files
            if (file_exists($tempOriginalPath)) {
                unlink($tempOriginalPath);
            }
            if (file_exists($tempThumbnailPath)) {
                unlink($tempThumbnailPath);
            }
        }
    }

    /**
     * Build thumbnail storage key from original key.
     *
     * Example: photos/folder/image.jpg → thumbs/photos/folder/image_thumb.jpg
     */
    private function buildThumbnailKey(string $originalKey): string
    {
        $pathInfo = PathInfo::fromPath($originalKey);
        $directory = $pathInfo->getDirnameOrEmpty();
        $thumbnailFileName = $pathInfo->getFilename().'_thumb.jpg';

        if ($directory === '') {
            return 'thumbs/'.$thumbnailFileName;
        }

        return 'thumbs/'.$directory.'/'.$thumbnailFileName;
    }
}
