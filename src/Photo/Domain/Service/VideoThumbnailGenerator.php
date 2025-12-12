<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service;

use App\Photo\Domain\Model\PathInfo;
use App\Shared\Infrastructure\Process\ProcessRunner;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function dirname;
use function escapeshellarg;
use function file_exists;
use function in_array;
use function is_array;
use function is_dir;
use function is_numeric;
use function json_decode;
use function min;
use function mkdir;
use function rename;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function trim;
use function unlink;

/**
 * Generate thumbnails from video files using FFmpeg.
 *
 * Uses ffprobe to get video duration and intelligently select a frame,
 * then FFmpeg to extract and resize that frame.
 *
 * Frame selection strategy:
 * - If duration unknown: use 1s
 * - If duration < 10s: use 1s
 * - Otherwise: use min(duration × 10%, 5s)
 */
class VideoThumbnailGenerator
{
    private const int DEFAULT_MAX_WIDTH = 300;
    private const int DEFAULT_MAX_HEIGHT = 300;
    private const int TIMEOUT_SECONDS = 10;
    private const int MAX_SOURCE_BYTES = 524288000; // 500 MiB for videos
    private const int JPEG_QUALITY = 2; // FFmpeg quality scale 2-31, lower is better

    /** @var string[] */
    private const array SUPPORTED_MIME_TYPES = [
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/mpeg',
        'video/3gpp',
        'video/x-flv',
    ];

    private bool $ffmpegAvailable;
    private bool $ffprobeAvailable;

    public function __construct(
        private readonly string $storagePath,
        private readonly LoggerInterface $logger,
        private readonly ProcessRunner $processRunner,
    ) {
        $this->ffmpegAvailable = $this->isCommandAvailable('ffmpeg');
        $this->ffprobeAvailable = $this->isCommandAvailable('ffprobe');
    }

    /**
     * Check if this generator supports the given MIME type.
     */
    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true);
    }

    /**
     * Check if FFmpeg is available for thumbnail generation.
     */
    public function isAvailable(): bool
    {
        return $this->ffmpegAvailable;
    }

    /**
     * Generate thumbnail from video file.
     *
     * @param string $sourceFilePath Relative or absolute path to source video
     * @param int    $maxWidth       Maximum width for thumbnail
     * @param int    $maxHeight      Maximum height for thumbnail
     *
     * @return string Relative path to generated thumbnail
     *
     * @throws InvalidArgumentException if path contains directory traversal
     * @throws RuntimeException         if generation fails
     */
    public function generateThumbnail(
        string $sourceFilePath,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $maxHeight = self::DEFAULT_MAX_HEIGHT,
    ): string {
        $this->validatePath($sourceFilePath);

        if (!$this->ffmpegAvailable) {
            throw new RuntimeException('FFmpeg is not available');
        }

        $absolutePath = $this->toAbsolutePath($sourceFilePath);

        if (!file_exists($absolutePath)) {
            throw new RuntimeException('Source video file not found');
        }

        // Check file size limit
        $size = filesize($absolutePath);
        if ($size !== false && $size > self::MAX_SOURCE_BYTES) {
            throw new RuntimeException(sprintf(
                'Source video too large for thumbnail generation (%d bytes, max %d)',
                $size,
                self::MAX_SOURCE_BYTES
            ));
        }

        // Compute thumbnail path
        $thumbnailRelativePath = $this->computeThumbnailRelativePath($sourceFilePath);
        $thumbnailAbsolutePath = $this->storagePath.'/'.$thumbnailRelativePath;

        // Idempotence: skip if already exists
        if (file_exists($thumbnailAbsolutePath)) {
            return $thumbnailRelativePath;
        }

        // Create thumbnail directory
        $thumbnailDir = dirname($thumbnailAbsolutePath);
        if (!is_dir($thumbnailDir) && !mkdir($thumbnailDir, 0755, true) && !is_dir($thumbnailDir)) {
            throw new RuntimeException('Failed to create thumbnail directory');
        }

        // Get video duration and select frame timestamp
        $frameTimestamp = $this->selectFrameTimestamp($absolutePath);

        // Generate thumbnail with FFmpeg (atomic write via temp file)
        $this->extractFrame($absolutePath, $thumbnailAbsolutePath, $frameTimestamp, $maxWidth, $maxHeight);

        $this->logger->info('Video thumbnail generated', [
            'source' => $sourceFilePath,
            'thumbnail' => $thumbnailRelativePath,
            'timestamp' => $frameTimestamp,
        ]);

        return $thumbnailRelativePath;
    }

    /**
     * Delete thumbnail file.
     */
    public function deleteThumbnail(string $thumbnailPath): void
    {
        $this->validatePath($thumbnailPath);

        $fullPath = $this->storagePath.'/'.$thumbnailPath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Check if a command is available on the system.
     * Protected to allow override in tests.
     */
    protected function isCommandAvailable(string $command): bool
    {
        // 1) Prefer array execution (no shell) if `which` exists in the environment
        try {
            $result = $this->processRunner->runArray(['which', $command], 2);

            if ($result->isSuccessful() && trim($result->stdout) !== '') {
                return true;
            }
        } catch (RuntimeException) {
            // Ignore and fall back
        }

        // 2) Fallback: use shell builtin (best-effort) to avoid false negatives in minimal images
        try {
            $result = $this->processRunner->run('command -v '.escapeshellarg($command).' 2>/dev/null', 2);

            return $result->isSuccessful() && trim($result->stdout) !== '';
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Select the best frame timestamp for thumbnail.
     *
     * Strategy:
     * - Duration unknown: 1s
     * - Duration < 10s: 1s
     * - Otherwise: min(duration × 10%, 5s)
     */
    private function selectFrameTimestamp(string $videoPath): float
    {
        if (!$this->ffprobeAvailable) {
            return 1.0;
        }

        $duration = $this->getVideoDuration($videoPath);

        if ($duration === null || $duration < 10.0) {
            return 1.0;
        }

        // Use 10% of duration, capped at 5 seconds
        return min($duration * 0.1, 5.0);
    }

    /**
     * Get video duration using ffprobe.
     */
    private function getVideoDuration(string $videoPath): ?float
    {
        try {
            $result = $this->processRunner->runArray([
                'ffprobe',
                '-v', 'error',
                '-print_format', 'json',
                '-show_entries', 'format=duration',
                $videoPath,
            ], self::TIMEOUT_SECONDS);

            if (!$result->isSuccessful()) {
                $this->logger->debug('ffprobe failed to get duration', [
                    'video' => $videoPath,
                    'stdout' => $result->stdout,
                    'stderr' => $result->stderr,
                ]);

                return null;
            }

            $data = json_decode($result->stdout, true);
            if (!is_array($data)) {
                return null;
            }

            $format = $data['format'] ?? null;
            if (!is_array($format)) {
                return null;
            }

            $duration = $format['duration'] ?? null;
            if (!is_numeric($duration)) {
                return null;
            }

            return (float) $duration;
        } catch (RuntimeException $e) {
            $this->logger->debug('ffprobe timed out', [
                'video' => $videoPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract a frame from video using FFmpeg.
     *
     * Uses atomic write: writes to temp file first, then renames.
     */
    private function extractFrame(
        string $videoPath,
        string $outputPath,
        float $timestamp,
        int $maxWidth,
        int $maxHeight,
    ): void {
        // Atomic write: write to temp file, then rename
        $tmpOutputPath = $outputPath.'.tmp';

        // Clean up any leftover temp file
        if (file_exists($tmpOutputPath)) {
            unlink($tmpOutputPath);
        }

        try {
            // FFmpeg arguments:
            // -nostdin: don't read from stdin (prevents hangs in backend context)
            // -hide_banner -loglevel error: reduce noise
            // -ss before -i: fast seek
            // -an -sn: ignore audio and subtitle streams
            // -frames:v 1: extract only 1 frame
            // -vf scale with out_range=pc (full/JPEG range) + format=yuv420p
            //     (avoids "deprecated pixel format" warnings on newer ffmpeg)
            // -q:v: JPEG quality (2=best, 31=worst)
            $result = $this->processRunner->runArray([
                'ffmpeg',
                '-nostdin',
                '-hide_banner',
                '-loglevel', 'error',
                '-ss', sprintf('%.3f', $timestamp),
                '-i', $videoPath,
                '-an',
                '-sn',
                '-frames:v', '1',
                '-vf', sprintf('scale=%d:%d:force_original_aspect_ratio=decrease:out_range=pc,format=yuv420p', $maxWidth, $maxHeight),
                '-q:v', (string) self::JPEG_QUALITY,
                '-y',
                $tmpOutputPath,
            ], self::TIMEOUT_SECONDS);

            if (!$result->isSuccessful()) {
                $this->logger->warning('FFmpeg frame extraction failed', [
                    'video' => $videoPath,
                    'timestamp' => $timestamp,
                    'exit_code' => $result->exitCode,
                    'stdout' => $result->stdout,
                    'stderr' => $result->stderr,
                ]);

                throw new RuntimeException('FFmpeg failed to extract frame: '.$result->getOutput());
            }

            if (!file_exists($tmpOutputPath)) {
                throw new RuntimeException('FFmpeg did not produce a thumbnail');
            }

            // Atomic rename (same filesystem)
            if (!rename($tmpOutputPath, $outputPath)) {
                throw new RuntimeException('Failed to move temporary thumbnail into place');
            }
        } finally {
            // Guaranteed cleanup of temp file
            if (file_exists($tmpOutputPath)) {
                unlink($tmpOutputPath);
            }
        }
    }

    private function computeThumbnailRelativePath(string $sourceFilePath): string
    {
        $pathInfo = PathInfo::fromPath($sourceFilePath);
        $relativePath = $pathInfo->getDirnameOrEmpty();
        $thumbnailFileName = $pathInfo->getFilename().'_thumb.jpg';

        if ($relativePath === '') {
            return 'thumbs/'.$thumbnailFileName;
        }

        return 'thumbs/'.$relativePath.'/'.$thumbnailFileName;
    }

    private function toAbsolutePath(string $sourceFilePath): string
    {
        if (str_starts_with($sourceFilePath, '/')) {
            return $sourceFilePath;
        }

        return $this->storagePath.'/'.$sourceFilePath;
    }

    private function validatePath(string $path): void
    {
        if (str_contains($path, '..')) {
            throw new InvalidArgumentException('Path cannot contain directory traversal (..)');
        }
    }
}
