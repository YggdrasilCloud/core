<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service\ThumbnailStrategy;

use App\Shared\Infrastructure\Process\ProcessRunner;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function file_exists;
use function in_array;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Thumbnail generation strategy for STL 3D model files.
 *
 * Uses stl-thumb to render a 3D preview of the model.
 */
final class StlThumbnailStrategy implements ThumbnailGeneratorStrategyInterface
{
    private const int STL_THUMB_TIMEOUT_SECONDS = 30;

    /** @var string[] */
    private const array SUPPORTED_MIME_TYPES = [
        'model/stl',
        'application/sla',
        'application/vnd.ms-pki.stl',
        'application/x-navistyle',
    ];

    private bool $stlThumbAvailable;

    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly LoggerInterface $logger,
    ) {
        try {
            // stl-thumb uses OSMesa for headless OpenGL rendering
            $this->stlThumbAvailable = $this->detectCommandAvailability('stl-thumb');
        } catch (Throwable $e) {
            $this->stlThumbAvailable = false;
            $this->logger->warning('Failed to detect stl-thumb availability', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    public function isAvailable(): bool
    {
        return $this->stlThumbAvailable;
    }

    public function generate(
        string $sourcePath,
        string $outputPath,
        int $maxWidth = 300,
        int $maxHeight = 300,
    ): void {
        if (!$this->stlThumbAvailable) {
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
            // stl-thumb arguments:
            // input.stl output.png: input and output files
            // -s SIZE: render size (uses max dimension)
            // -b RRGGBBAA: background color with alpha (white opaque = FFFFFFFF)
            $size = max($maxWidth, $maxHeight);
            $result = $this->processRunner->runArray([
                'stl-thumb',
                $sourcePath,
                $tempPng,
                '-s', (string) $size,
                '-b', 'FFFFFFFF',
            ], self::STL_THUMB_TIMEOUT_SECONDS);

            if (!$result->isSuccessful() || !file_exists($tempPng)) {
                throw new RuntimeException('stl-thumb failed to generate STL thumbnail: '.$result->getOutput().$result->stderr);
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
     * Convert PNG to JPEG with resizing.
     */
    private function convertPngToJpeg(string $pngPath, string $jpegPath, int $maxWidth, int $maxHeight): void
    {
        $image = @imagecreatefrompng($pngPath);
        if ($image === false) {
            throw new RuntimeException('Failed to load PNG image');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio < 1) {
            $newWidth = max(1, (int) ($width * $ratio));
            $newHeight = max(1, (int) ($height * $ratio));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized === false) {
                imagedestroy($image);

                throw new RuntimeException('Failed to create resized image');
            }

            // Fill with white background (for transparency)
            $white = imagecolorallocate($resized, 255, 255, 255);
            if ($white !== false) {
                imagefill($resized, 0, 0, $white);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        // Save as JPEG
        $success = imagejpeg($image, $jpegPath, 85);
        imagedestroy($image);

        if (!$success) {
            throw new RuntimeException('Failed to save JPEG thumbnail');
        }
    }

    /**
     * Detect if a command is available using 'which' (POSIX portable).
     */
    private function detectCommandAvailability(string $command): bool
    {
        $result = $this->processRunner->runArray(['which', $command]);

        return $result->isSuccessful() && $result->stdout !== '';
    }
}
