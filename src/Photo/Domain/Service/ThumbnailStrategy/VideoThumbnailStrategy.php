<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service\ThumbnailStrategy;

use App\Shared\Infrastructure\Process\ProcessRunner;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function file_exists;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function min;

use const JSON_THROW_ON_ERROR;

/**
 * Thumbnail generation strategy for videos.
 *
 * Uses FFmpeg to extract a frame from the video.
 * Frame selection strategy:
 * - If duration unknown: use 1s
 * - If duration < 10s: use 1s
 * - Otherwise: use min(duration × 10%, 5s)
 */
final class VideoThumbnailStrategy implements ThumbnailGeneratorStrategyInterface
{
    private const int JPEG_QUALITY = 2; // FFmpeg quality scale 2-31, lower is better
    private const int FFMPEG_TIMEOUT_SECONDS = 60;
    private const int FFPROBE_TIMEOUT_SECONDS = 10;

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
        private readonly ProcessRunner $processRunner,
        private readonly LoggerInterface $logger,
    ) {
        try {
            $this->ffmpegAvailable = $this->detectCommandAvailability('ffmpeg');
            $this->ffprobeAvailable = $this->detectCommandAvailability('ffprobe');
        } catch (Throwable $e) {
            $this->ffmpegAvailable = false;
            $this->ffprobeAvailable = false;
            $this->logger->warning('Failed to detect ffmpeg/ffprobe availability', [
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
        return $this->ffmpegAvailable;
    }

    public function generate(
        string $sourcePath,
        string $outputPath,
        int $maxWidth = 300,
        int $maxHeight = 300,
    ): void {
        if (!$this->ffmpegAvailable) {
            throw new RuntimeException('FFmpeg is not available');
        }

        $timestamp = $this->selectFrameTimestamp($sourcePath);

        // FFmpeg arguments:
        // -nostdin: don't read from stdin
        // -hide_banner -loglevel error: reduce noise
        // -ss before -i: fast seek
        // -an -sn: ignore audio and subtitle streams
        // -frames:v 1: extract only 1 frame
        // -vf scale: resize maintaining aspect ratio
        //     format=yuv420p: ensure compatibility
        // -color_range pc: use full range (0-255) for JPEG
        // -q:v: JPEG quality (2=best, 31=worst)
        $result = $this->processRunner->runArray([
            'ffmpeg',
            '-nostdin',
            '-hide_banner',
            '-loglevel', 'error',
            '-ss', (string) $timestamp,
            '-i', $sourcePath,
            '-an',
            '-sn',
            '-frames:v', '1',
            '-vf', "scale={$maxWidth}:{$maxHeight}:force_original_aspect_ratio=decrease,format=yuv420p",
            '-color_range', 'pc',
            '-q:v', (string) self::JPEG_QUALITY,
            '-y',
            $outputPath,
        ], self::FFMPEG_TIMEOUT_SECONDS);

        if (!$result->isSuccessful() || !file_exists($outputPath)) {
            throw new RuntimeException('FFmpeg failed to generate video thumbnail: '.$result->getOutput());
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

    /**
     * Select the best frame timestamp for video thumbnail.
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

        $result = $this->processRunner->runArray([
            'ffprobe',
            '-v', 'error',
            '-print_format', 'json',
            '-show_entries', 'format=duration',
            $videoPath,
        ], self::FFPROBE_TIMEOUT_SECONDS);

        if (!$result->isSuccessful() || $result->stdout === '') {
            return 1.0;
        }

        try {
            $data = json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 1.0;
        }

        if (!is_array($data)) {
            return 1.0;
        }

        $format = $data['format'] ?? null;
        if (!is_array($format)) {
            return 1.0;
        }

        $duration = $format['duration'] ?? null;
        if (!is_numeric($duration)) {
            return 1.0;
        }

        $duration = (float) $duration;

        if ($duration < 10.0) {
            return 1.0;
        }

        return min($duration * 0.1, 5.0);
    }
}
