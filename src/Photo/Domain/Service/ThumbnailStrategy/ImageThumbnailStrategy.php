<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service\ThumbnailStrategy;

use App\Shared\Infrastructure\Process\ProcessRunner;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function file_exists;
use function getimagesize;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromgif;
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagecreatefromwebp;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagejpeg;
use function in_array;
use function max;
use function min;

/**
 * Thumbnail generation strategy for images.
 *
 * Uses vipsthumbnail (libvips) when available for better performance,
 * falls back to PHP GD otherwise.
 */
final class ImageThumbnailStrategy implements ThumbnailGeneratorStrategyInterface
{
    private const int JPEG_QUALITY = 85;
    private const int VIPS_TIMEOUT_SECONDS = 30;

    /** @var string[] */
    private const array SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private bool $vipsAvailable;

    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly LoggerInterface $logger,
    ) {
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
        $result = $this->processRunner->runArray(['command', '-v', 'vipsthumbnail']);

        return $result->isSuccessful() && $result->stdout !== '';
    }

    private function generateWithVips(
        string $sourcePath,
        string $outputPath,
        int $maxWidth,
        int $maxHeight,
    ): void {
        $result = $this->processRunner->runArray([
            'vipsthumbnail',
            $sourcePath,
            '-s', $maxWidth.'x'.$maxHeight,
            '-o', $outputPath.'[Q='.self::JPEG_QUALITY.']',
        ], self::VIPS_TIMEOUT_SECONDS);

        if (!$result->isSuccessful() || !file_exists($outputPath)) {
            $this->logger->warning('vipsthumbnail failed, falling back to GD', [
                'source' => $sourcePath,
                'exitCode' => $result->exitCode,
                'error' => $result->stderr,
            ]);

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

        // Fill with white background for transparent images (PNG, GIF, WebP)
        // JPEG output doesn't support transparency, so we need a solid background
        $white = imagecolorallocate($thumbnail, 255, 255, 255);
        if ($white !== false) {
            imagefill($thumbnail, 0, 0, $white);
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
