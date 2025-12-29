<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service\ThumbnailStrategy;

use RuntimeException;

use function escapeshellarg;
use function exec;
use function file_exists;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function min;
use function sprintf;

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

    public function __construct()
    {
        $this->ffmpegAvailable = $this->detectCommandAvailability('ffmpeg');
        $this->ffprobeAvailable = $this->detectCommandAvailability('ffprobe');
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
        // -q:v: JPEG quality (2=best, 31=worst)
        $command = sprintf(
            'ffmpeg -nostdin -hide_banner -loglevel error -ss %.3f -i %s -an -sn -frames:v 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease" -q:v %d -y %s 2>&1',
            $timestamp,
            escapeshellarg($sourcePath),
            $maxWidth,
            $maxHeight,
            self::JPEG_QUALITY,
            escapeshellarg($outputPath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            throw new RuntimeException('FFmpeg failed to generate video thumbnail: '.implode("\n", $output));
        }
    }

    private function detectCommandAvailability(string $command): bool
    {
        $output = [];
        $returnCode = 0;
        exec('command -v '.escapeshellarg($command).' 2>/dev/null', $output, $returnCode);

        return $returnCode === 0 && !empty($output);
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

        $output = [];
        $returnCode = 0;
        exec(sprintf(
            'ffprobe -v error -print_format json -show_entries format=duration %s 2>/dev/null',
            escapeshellarg($videoPath)
        ), $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            return 1.0;
        }

        $data = json_decode(implode('', $output), true);
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
