<?php

declare(strict_types=1);

namespace App\Photo\Application\Service;

use App\File\Domain\Model\StoredObject;
use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Application\Port\ThumbnailServiceInterface;
use App\Photo\Domain\Model\PathInfo;
use App\Photo\Domain\Service\ThumbnailGenerator;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function fclose;
use function fopen;

/**
 * Application service for thumbnail generation.
 *
 * Orchestrates thumbnail generation across different storage adapters:
 * - For local storage: direct filesystem access via ThumbnailGenerator
 * - For remote storage (S3): download → generate → upload
 */
final readonly class ThumbnailService implements ThumbnailServiceInterface
{
    private const LOCAL_ADAPTER = 'local';

    public function __construct(
        private ThumbnailGenerator $thumbnailGenerator,
        private FileStorageInterface $fileStorage,
        private LoggerInterface $logger,
        private string $tempDir,
    ) {}

    /**
     * Generate a thumbnail for a stored file.
     *
     * @param StoredObject $storedObject The original file metadata
     *
     * @return null|string The storage key of the generated thumbnail, or null if generation failed
     */
    public function generateThumbnail(StoredObject $storedObject): ?string
    {
        try {
            if ($storedObject->adapter === self::LOCAL_ADAPTER) {
                return $this->thumbnailGenerator->generateThumbnail($storedObject->key);
            }

            return $this->generateForRemoteStorage($storedObject);
        } catch (RuntimeException $e) {
            $this->logger->warning('Thumbnail generation failed', [
                'key' => $storedObject->key,
                'adapter' => $storedObject->adapter,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate thumbnail for remote storage (S3, MinIO, etc.).
     *
     * Flow:
     * 1. Download original from remote storage to temp file
     * 2. Generate thumbnail locally using vips/GD
     * 3. Upload thumbnail to remote storage
     * 4. Clean up temp files
     */
    private function generateForRemoteStorage(StoredObject $storedObject): string
    {
        $this->logger->debug('Generating thumbnail for remote storage', [
            'key' => $storedObject->key,
            'adapter' => $storedObject->adapter,
        ]);

        // Ensure temp directory exists
        if (!is_dir($this->tempDir) && !mkdir($this->tempDir, 0755, true) && !is_dir($this->tempDir)) {
            throw new RuntimeException('Failed to create temp directory: '.$this->tempDir);
        }

        // Download original to temp file
        $originalStream = $this->fileStorage->readStream($storedObject->key);
        $tempOriginalPath = $this->tempDir.'/ygg_orig_'.uniqid().'.tmp';

        try {
            $tempFile = fopen($tempOriginalPath, 'w');
            if ($tempFile === false) {
                throw new RuntimeException('Failed to create temp file');
            }

            stream_copy_to_stream($originalStream, $tempFile);
            fclose($tempFile);
            fclose($originalStream);

            // Generate thumbnail using vips/GD
            $tempThumbnailPath = $this->generateThumbnailFromTempFile($tempOriginalPath, $storedObject->key);

            try {
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
                // Clean up temp thumbnail
                if (file_exists($tempThumbnailPath)) {
                    unlink($tempThumbnailPath);
                }
            }
        } finally {
            // Clean up temp original
            if (file_exists($tempOriginalPath)) {
                unlink($tempOriginalPath);
            }
        }
    }

    /**
     * Generate thumbnail from a temporary file path.
     *
     * @return string Path to the generated thumbnail
     */
    private function generateThumbnailFromTempFile(string $sourcePath, string $originalKey): string
    {
        $pathInfo = PathInfo::fromPath($originalKey);
        $thumbnailPath = $this->tempDir.'/'.$pathInfo->getFilename().'_thumb.jpg';

        // Use vipsthumbnail if available, otherwise GD
        if ($this->isVipsAvailable()) {
            $this->generateWithVips($sourcePath, $thumbnailPath);
        } else {
            $this->generateWithGd($sourcePath, $thumbnailPath);
        }

        return $thumbnailPath;
    }

    private function isVipsAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('command -v vipsthumbnail 2>/dev/null', $output, $returnCode);

        return $returnCode === 0 && !empty($output);
    }

    private function generateWithVips(string $sourcePath, string $outputPath, int $maxWidth = 300, int $maxHeight = 300): void
    {
        $command = sprintf(
            'vipsthumbnail %s -s %dx%d -o %s 2>&1',
            escapeshellarg($sourcePath),
            $maxWidth,
            $maxHeight,
            escapeshellarg($outputPath.'[Q=85]')
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            throw new RuntimeException('vipsthumbnail failed: '.implode("\n", $output));
        }
    }

    private function generateWithGd(string $sourcePath, string $outputPath, int $maxWidth = 300, int $maxHeight = 300): void
    {
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new RuntimeException('Cannot read image info from: '.$sourcePath);
        }

        [$width, $height, $type] = $imageInfo;

        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default => throw new RuntimeException('Unsupported image type: '.$type),
        };

        if ($source === false) {
            throw new RuntimeException('Failed to load image: '.$sourcePath);
        }

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int) ($width * $ratio));
        $newHeight = max(1, (int) ($height * $ratio));

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        if ($thumbnail === false) {
            imagedestroy($source);

            throw new RuntimeException('Failed to create thumbnail canvas');
        }

        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        if (!imagejpeg($thumbnail, $outputPath, 85)) {
            imagedestroy($thumbnail);

            throw new RuntimeException('Failed to save thumbnail');
        }

        imagedestroy($thumbnail);
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
