<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service\ThumbnailStrategy;

/**
 * Strategy interface for thumbnail generation.
 *
 * Implementations handle specific media types (images, videos, PDFs, etc.)
 * and know how to generate thumbnails from them.
 */
interface ThumbnailGeneratorStrategyInterface
{
    /**
     * Check if this strategy supports the given MIME type.
     */
    public function supports(string $mimeType): bool;

    /**
     * Generate a thumbnail from the source file.
     *
     * @param string $sourcePath Absolute path to the source file
     * @param string $outputPath Absolute path where the thumbnail should be saved (JPEG)
     * @param int    $maxWidth   Maximum width for the thumbnail
     * @param int    $maxHeight  Maximum height for the thumbnail
     */
    public function generate(
        string $sourcePath,
        string $outputPath,
        int $maxWidth = 300,
        int $maxHeight = 300,
    ): void;

    /**
     * Check if this strategy is available (required tools are installed).
     */
    public function isAvailable(): bool;
}
