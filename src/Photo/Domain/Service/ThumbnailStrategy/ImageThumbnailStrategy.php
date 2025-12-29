<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service\ThumbnailStrategy;

use RuntimeException;

use function escapeshellarg;
use function exec;
use function file_exists;
use function getimagesize;
use function imagecopyresampled;
use function imagecreatefromgif;
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagecreatefromwebp;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagejpeg;
use function in_array;
use function max;
use function min;
use function sprintf;

/**
 * Thumbnail generation strategy for images.
 *
 * Uses vipsthumbnail (libvips) when available for better performance,
 * falls back to PHP GD otherwise.
 */
final class ImageThumbnailStrategy implements ThumbnailGeneratorStrategyInterface
{
    private const int JPEG_QUALITY = 85;

    /** @var string[] */
    private const array SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private bool $vipsAvailable;

    public function __construct()
    {
        $this->vipsAvailable = $this->detectVipsAvailability();
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function isAvailable(): bool
    {
        // GD is always available as fallback
        return true;
    }

    public function generate(
        string $sourcePath,
        string $outputPath,
        int $maxWidth = 300,
        int $maxHeight = 300,
    ): void {
        if ($this->vipsAvailable) {
            $this->generateWithVips($sourcePath, $outputPath, $maxWidth, $maxHeight);
        } else {
            $this->generateWithGd($sourcePath, $outputPath, $maxWidth, $maxHeight);
        }
    }

    private function detectVipsAvailability(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('command -v vipsthumbnail 2>/dev/null', $output, $returnCode);

        return $returnCode === 0 && !empty($output);
    }

    private function generateWithVips(
        string $sourcePath,
        string $outputPath,
        int $maxWidth,
        int $maxHeight,
    ): void {
        $command = sprintf(
            'vipsthumbnail %s -s %dx%d -o %s 2>&1',
            escapeshellarg($sourcePath),
            $maxWidth,
            $maxHeight,
            escapeshellarg($outputPath.'[Q='.self::JPEG_QUALITY.']')
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            // Fallback to GD if vips fails
            $this->generateWithGd($sourcePath, $outputPath, $maxWidth, $maxHeight);
        }
    }

    private function generateWithGd(
        string $sourcePath,
        string $outputPath,
        int $maxWidth,
        int $maxHeight,
    ): void {
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

        if (!imagejpeg($thumbnail, $outputPath, self::JPEG_QUALITY)) {
            imagedestroy($thumbnail);

            throw new RuntimeException('Failed to save thumbnail');
        }

        imagedestroy($thumbnail);
    }
}
