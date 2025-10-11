<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service;

final readonly class ThumbnailGenerator
{
    private bool $vipsAvailable;

    public function __construct(
        private string $storagePath,
    ) {
        // Détecter si vipsthumbnail est disponible
        $this->vipsAvailable = $this->isVipsAvailable();
    }

    private function isVipsAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('command -v vipsthumbnail 2>/dev/null', $output, $returnCode);
        return $returnCode === 0 && !empty($output);
    }

    /**
     * Get the method used for thumbnail generation.
     * Useful for debugging and monitoring.
     */
    public function getMethod(): string
    {
        return $this->vipsAvailable ? 'vipsthumbnail (libvips)' : 'PHP GD';
    }

    /**
     * Generate thumbnail from source image file.
     * Uses vipsthumbnail if available (faster, less memory), falls back to GD.
     *
     * @param string $sourceFilePath Relative or absolute path to source image
     * @param int $maxWidth Maximum width for thumbnail
     * @param int $maxHeight Maximum height for thumbnail
     * @return string Relative path to generated thumbnail
     */
    public function generateThumbnail(string $sourceFilePath, int $maxWidth = 300, int $maxHeight = 300): string
    {
        if ($this->vipsAvailable) {
            return $this->generateWithVips($sourceFilePath, $maxWidth, $maxHeight);
        }

        return $this->generateWithGd($sourceFilePath, $maxWidth, $maxHeight);
    }

    /**
     * Generate thumbnail using vipsthumbnail (libvips).
     * Fast, memory-efficient, high quality.
     */
    private function generateWithVips(string $sourceFilePath, int $maxWidth, int $maxHeight): string
    {
        // Convert relative path to absolute if needed
        $absolutePath = $sourceFilePath;
        if (!str_starts_with($sourceFilePath, '/')) {
            $absolutePath = $this->storagePath . '/' . $sourceFilePath;
        }

        if (!file_exists($absolutePath)) {
            throw new \RuntimeException('Source file not found');
        }

        // Generate thumbnail path
        $pathInfo = pathinfo($sourceFilePath);
        $relativePath = $pathInfo['dirname'] === '.' ? '' : $pathInfo['dirname'];
        $thumbnailDir = $this->storagePath . '/thumbs/' . $relativePath;

        // Create thumbnail directory if it doesn't exist
        if (!is_dir($thumbnailDir) && !mkdir($thumbnailDir, 0755, true) && !is_dir($thumbnailDir)) {
            throw new \RuntimeException('Failed to create thumbnail directory');
        }

        // vipsthumbnail outputs as filename.jpg by default, we want filename_thumb.jpg
        $thumbnailFileName = $pathInfo['filename'] . '_thumb.jpg';
        $thumbnailPath = $thumbnailDir . '/' . $thumbnailFileName;

        // Build vipsthumbnail command
        // -s WxH: size (max dimensions, maintains aspect ratio)
        // -o: output with quality settings [Q=85]
        // --eprofile: embed color profile for better color accuracy
        $command = sprintf(
            'vipsthumbnail %s -s %dx%d -o %s[Q=85] 2>&1',
            escapeshellarg($absolutePath),
            $maxWidth,
            $maxHeight,
            escapeshellarg($thumbnailPath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($thumbnailPath)) {
            // Si vipsthumbnail échoue, fallback sur GD
            return $this->generateWithGd($sourceFilePath, $maxWidth, $maxHeight);
        }

        // Return relative path from storage root
        return 'thumbs/' . $relativePath . '/' . $thumbnailFileName;
    }

    /**
     * Generate thumbnail using PHP GD.
     * Fallback method, works everywhere.
     */
    private function generateWithGd(string $sourceFilePath, int $maxWidth, int $maxHeight): string
    {
        // Convert relative path to absolute if needed
        $absolutePath = $sourceFilePath;
        if (!str_starts_with($sourceFilePath, '/')) {
            $absolutePath = $this->storagePath . '/' . $sourceFilePath;
        }

        // Get image info
        $imageInfo = getimagesize($absolutePath);
        if ($imageInfo === false) {
            throw new \RuntimeException('Failed to read image info');
        }

        [$width, $height, $type] = $imageInfo;

        // Load source image based on type
        $sourceImage = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => imagecreatefrompng($absolutePath),
            IMAGETYPE_GIF => imagecreatefromgif($absolutePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($absolutePath),
            default => throw new \RuntimeException('Unsupported image type'),
        };

        if ($sourceImage === false) {
            throw new \RuntimeException('Failed to create image from file');
        }

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        if ($thumbnail === false) {
            imagedestroy($sourceImage);
            throw new \RuntimeException('Failed to create thumbnail');
        }

        // Preserve transparency for PNG and GIF
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }

        // Resize
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Generate thumbnail path (same structure as original but in thumbs/ subdirectory)
        // Use the original relative path to determine directory structure
        $pathInfo = pathinfo($sourceFilePath);
        $relativePath = $pathInfo['dirname'] === '.' ? '' : $pathInfo['dirname'];
        $thumbnailDir = $this->storagePath . '/thumbs/' . $relativePath;

        // Create thumbnail directory if it doesn't exist
        if (!is_dir($thumbnailDir) && !mkdir($thumbnailDir, 0755, true) && !is_dir($thumbnailDir)) {
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            throw new \RuntimeException('Failed to create thumbnail directory');
        }

        $thumbnailFileName = $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
        $thumbnailPath = $thumbnailDir . '/' . $thumbnailFileName;

        // Save thumbnail
        $saved = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($thumbnail, $thumbnailPath, 85),
            IMAGETYPE_PNG => imagepng($thumbnail, $thumbnailPath, 8),
            IMAGETYPE_GIF => imagegif($thumbnail, $thumbnailPath),
            IMAGETYPE_WEBP => imagewebp($thumbnail, $thumbnailPath, 85),
            default => false,
        };

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        if (!$saved) {
            throw new \RuntimeException('Failed to save thumbnail');
        }

        // Return relative path from storage root
        return 'thumbs/' . $relativePath . '/' . $thumbnailFileName;
    }

    /**
     * Delete thumbnail file.
     */
    public function deleteThumbnail(string $thumbnailPath): void
    {
        $fullPath = $this->storagePath . '/' . $thumbnailPath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
