<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service\ThumbnailStrategy;

use App\Shared\Infrastructure\Process\ProcessRunner;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function file_exists;
use function in_array;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Thumbnail generation strategy for STL 3D model files.
 *
 * NOTE: stl-thumb with OSMesa (headless rendering) is currently unreliable
 * for complex models in Docker environments. It produces corrupted/noisy
 * images. This strategy is disabled until stl-thumb fixes this issue.
 *
 * @see https://github.com/unlimitedbacon/stl-thumb/issues/XXX
 */
final class StlThumbnailStrategy implements ThumbnailGeneratorStrategyInterface
{
    private const int STL_THUMB_TIMEOUT_SECONDS = 30;

    /**
     * Render sizes to try for OSMesa headless rendering.
     * OSMesa in Docker can produce corrupted output at certain sizes.
     * We try from largest to smallest and pick the first that works.
     *
     * @var int[]
     */
    private const array OSMESA_RENDER_SIZES = [200, 150, 100, 75];

    /** @var string[] */
    private const array SUPPORTED_MIME_TYPES = [
        'model/stl',
        'application/sla',
        'application/vnd.ms-pki.stl',
        'application/x-navistyle',
    ];

    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly LoggerInterface $logger,
    ) {}

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function isAvailable(): bool
    {
        // Disabled due to OSMesa rendering bugs in headless Docker
        // @see https://github.com/unlimitedbacon/stl-thumb - complex models produce noise
        return false;
    }

    public function generate(
        string $sourcePath,
        string $outputPath,
        int $maxWidth = 300,
        int $maxHeight = 300,
    ): void {
        if (!$this->isAvailable()) {
            throw new RuntimeException('stl-thumb is not available');
        }

        // stl-thumb outputs PNG by default, we need to convert to JPEG
        $tempPng = tempnam(sys_get_temp_dir(), 'stl_thumb_');
        if ($tempPng === false) {
            throw new RuntimeException('Failed to create temp file for STL thumbnail');
        }
        $tempPng .= '.png';

        try {
            // stl-thumb uses OSMesa for headless OpenGL rendering
            // OSMesa can produce corrupted (noise) output at certain sizes
            // Try multiple sizes and use the first one that produces valid output
            $validPng = false;
            foreach (self::OSMESA_RENDER_SIZES as $renderSize) {
                $result = $this->processRunner->runArray([
                    'stl-thumb',
                    $sourcePath,
                    $tempPng,
                    '-s', (string) $renderSize,
                    '-a', 'none',
                ], self::STL_THUMB_TIMEOUT_SECONDS);

                if ($result->isSuccessful() && file_exists($tempPng) && $this->isValidPng($tempPng)) {
                    $validPng = true;
                    $this->logger->debug('STL thumbnail rendered successfully', [
                        'size' => $renderSize,
                        'source' => $sourcePath,
                    ]);

                    break;
                }

                // Clean up failed attempt
                if (file_exists($tempPng)) {
                    unlink($tempPng);
                }
            }

            if (!$validPng) {
                throw new RuntimeException('stl-thumb failed to generate valid STL thumbnail at any size');
            }

            // Convert PNG to JPEG using ImageMagick or GD
            $this->convertPngToJpeg($tempPng, $outputPath, $maxWidth, $maxHeight);
        } finally {
            // Cleanup temp PNG
            if (file_exists($tempPng)) {
                unlink($tempPng);
            }
        }
    }

    /**
     * Convert PNG to JPEG with resizing (up or down).
     */
    private function convertPngToJpeg(string $pngPath, string $jpegPath, int $maxWidth, int $maxHeight): void
    {
        $image = @imagecreatefrompng($pngPath);
        if ($image === false) {
            throw new RuntimeException('Failed to load PNG image');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate target dimensions (fit within max bounds, minimum 1px)
        $targetSize = max(1, min($maxWidth, $maxHeight));
        $newWidth = $targetSize;
        $newHeight = $targetSize;

        // Create target image with white background
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($resized === false) {
            imagedestroy($image);

            throw new RuntimeException('Failed to create resized image');
        }

        // Fill with white background (for PNG transparency)
        $white = imagecolorallocate($resized, 255, 255, 255);
        if ($white !== false) {
            imagefill($resized, 0, 0, $white);
        }

        // Copy and resize the image
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        // Save as JPEG
        $success = imagejpeg($resized, $jpegPath, 85);
        imagedestroy($resized);

        if (!$success) {
            throw new RuntimeException('Failed to save JPEG thumbnail');
        }
    }

    /**
     * Check if PNG is valid (not corrupted/noise).
     *
     * OSMesa can produce images that look like TV static noise.
     * We detect this by checking for high-frequency color changes.
     */
    private function isValidPng(string $pngPath): bool
    {
        $image = @imagecreatefrompng($pngPath);
        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Check middle row for abrupt color changes (noise detection)
        $changes = 0;
        $lastColor = null;
        $middleY = (int) ($height / 2);

        for ($x = 0; $x < $width; ++$x) {
            $color = imagecolorat($image, $x, $middleY) & 0xFFFFFF; // Ignore alpha
            if ($lastColor !== null) {
                // Large color difference between adjacent pixels = noise
                $diff = abs($color - $lastColor);
                if ($diff > 50000) {
                    ++$changes;
                }
            }
            $lastColor = $color;
        }

        imagedestroy($image);

        // If more than 50% of pixels have abrupt changes, it's noise
        $noiseThreshold = $width * 0.5;

        return $changes < $noiseThreshold;
    }
}
