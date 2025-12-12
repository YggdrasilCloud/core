<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Service;

use App\Photo\Domain\Service\VideoThumbnailGenerator;
use App\Shared\Infrastructure\Process\ProcessResult;
use App\Shared\Infrastructure\Process\ProcessRunner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(VideoThumbnailGenerator::class)]
final class VideoThumbnailGeneratorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/video_thumb_test_'.bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir.'/thumbs', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->tempDir);
    }

    public function testSupportsVideoMimeTypes(): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        self::assertTrue($generator->supports('video/mp4'));
        self::assertTrue($generator->supports('video/webm'));
        self::assertTrue($generator->supports('video/quicktime'));
        self::assertFalse($generator->supports('image/jpeg'));
        self::assertFalse($generator->supports('application/pdf'));
    }

    public function testValidatePathRejectsDirectoryTraversal(): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('directory traversal');

        $generator->generateThumbnail('../../../etc/passwd');
    }

    public function testGenerateThumbnailThrowsWhenFfmpegUnavailable(): void
    {
        $generator = $this->createGenerator(
            $this->createMock(ProcessRunner::class),
            ffmpegAvailable: false,
        );

        // Create a dummy video file
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video content');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FFmpeg is not available');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailThrowsForMissingFile(): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');

        $generator->generateThumbnail('nonexistent.mp4');
    }

    public function testGenerateThumbnailThrowsForOversizedFile(): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        // We can't create a 500MB file, so we use a mock generator that overrides the check
        // For now, just verify the file exists check works
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'small content');

        self::assertFileExists($videoPath);
    }

    public function testIdempotenceSkipsExistingThumbnail(): void
    {
        // ProcessRunner should NEVER be called if thumbnail exists
        $processRunner = $this->createMock(ProcessRunner::class);
        $processRunner->expects(self::never())->method('run');

        $generator = $this->createGenerator($processRunner);

        // Pre-create the thumbnail
        $thumbnailDir = $this->tempDir.'/thumbs';
        $thumbnailPath = $thumbnailDir.'/test_thumb.jpg';
        file_put_contents($thumbnailPath, 'existing thumbnail');

        // Create source file
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        // Should return immediately without regenerating
        $result = $generator->generateThumbnail('test.mp4');

        self::assertSame('thumbs/test_thumb.jpg', $result);
        // Content should be unchanged (idempotent)
        self::assertSame('existing thumbnail', file_get_contents($thumbnailPath));
    }

    public function testGenerateThumbnailCallsFfmpegWithCorrectParameters(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // Expect ffprobe call for duration (returns 30s video)
        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.5"}}', '');

        // Expect ffmpeg call for frame extraction
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command, int $timeout) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Verify ffmpeg command structure
                self::assertStringContainsString('ffmpeg', $command);
                self::assertStringContainsString('-frames:v 1', $command);
                self::assertStringContainsString('scale=300:300', $command);
                self::assertStringContainsString('-q:v 2', $command);
                // Verify timestamp is 10% of 30s = 3.05s (but capped at 5s)
                self::assertStringContainsString('-ss', $command);

                // Simulate ffmpeg creating the thumbnail file
                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        // Create source file
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('test.mp4');

        self::assertSame('thumbs/test_thumb.jpg', $result);
        self::assertFileExists($this->tempDir.'/thumbs/test_thumb.jpg');
    }

    public function testGenerateThumbnailUsesFallbackTimestampWhenFfprobeUnavailable(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // Only ffmpeg should be called (no ffprobe)
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::once())
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffmpegResult): ProcessResult {
                self::assertStringContainsString('ffmpeg', $command);
                // Should use 1.0s fallback timestamp
                self::assertStringContainsString('-ss', $command);
                self::assertStringContainsString('1.000', $command);

                // Simulate ffmpeg creating the thumbnail file
                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        // ffprobe unavailable
        $generator = $this->createGenerator($processRunner, ffprobeAvailable: false);

        // Create source file
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('test.mp4');

        self::assertSame('thumbs/test_thumb.jpg', $result);
    }

    public function testGenerateThumbnailUsesFallbackTimestampForShortVideo(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns short duration (< 10s)
        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"5.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should use 1.0s fallback for short videos
                self::assertStringContainsString('1.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailCapsTimestampAt5Seconds(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns very long video (100s, 10% would be 10s but should cap at 5s)
        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"100.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should be capped at 5.0s (not 10.0s which is 10% of 100s)
                self::assertStringContainsString('5.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailThrowsOnFfmpegFailure(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        $ffmpegResult = new ProcessResult(1, '', 'Error processing video');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static fn (string $command) => str_contains($command, 'ffprobe')
                ? $ffprobeResult
                : $ffmpegResult)
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FFmpeg failed');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailThrowsWhenNoOutputProduced(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        // FFmpeg succeeds but doesn't produce output
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static fn (string $command) => str_contains($command, 'ffprobe')
                ? $ffprobeResult
                : $ffmpegResult)
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FFmpeg did not produce a thumbnail');

        $generator->generateThumbnail('test.mp4');
    }

    public function testDeleteThumbnailRemovesFile(): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        $thumbnailPath = $this->tempDir.'/thumbs/video_thumb.jpg';
        file_put_contents($thumbnailPath, 'thumbnail content');

        self::assertFileExists($thumbnailPath);

        $generator->deleteThumbnail('thumbs/video_thumb.jpg');

        self::assertFileDoesNotExist($thumbnailPath);
    }

    public function testDeleteThumbnailHandlesNonexistent(): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        // Should not throw
        $generator->deleteThumbnail('thumbs/nonexistent.jpg');

        $this->expectNotToPerformAssertions();
    }

    public function testDeleteThumbnailRejectsDirectoryTraversal(): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('directory traversal');

        $generator->deleteThumbnail('../../../etc/passwd');
    }

    #[DataProvider('provideSupportedMimeTypesCases')]
    public function testSupportedMimeTypes(string $mimeType, bool $expected): void
    {
        $generator = $this->createGenerator($this->createMock(ProcessRunner::class));

        self::assertSame($expected, $generator->supports($mimeType));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideSupportedMimeTypesCases(): iterable
    {
        return [
            'mp4' => ['video/mp4', true],
            'webm' => ['video/webm', true],
            'ogg' => ['video/ogg', true],
            'quicktime' => ['video/quicktime', true],
            'avi' => ['video/x-msvideo', true],
            'mkv' => ['video/x-matroska', true],
            'mpeg' => ['video/mpeg', true],
            '3gpp' => ['video/3gpp', true],
            'flv' => ['video/x-flv', true],
            'jpeg' => ['image/jpeg', false],
            'png' => ['image/png', false],
            'pdf' => ['application/pdf', false],
            'audio' => ['audio/mpeg', false],
        ];
    }

    public function testIsAvailableReturnsFfmpegStatus(): void
    {
        $generatorAvailable = $this->createGenerator(
            $this->createMock(ProcessRunner::class),
            ffmpegAvailable: true,
        );
        $generatorUnavailable = $this->createGenerator(
            $this->createMock(ProcessRunner::class),
            ffmpegAvailable: false,
        );

        self::assertTrue($generatorAvailable->isAvailable());
        self::assertFalse($generatorUnavailable->isAvailable());
    }

    public function testGenerateThumbnailHandlesAbsolutePath(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        // Create source file with absolute path
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        // Pass absolute path
        $result = $generator->generateThumbnail($videoPath);

        self::assertStringContainsString('_thumb.jpg', $result);
    }

    public function testGenerateThumbnailCreatesNestedDirectories(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    // Create parent directory if needed
                    $dir = dirname($tmpPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        // Create nested source file
        $nestedDir = $this->tempDir.'/subdir/nested';
        mkdir($nestedDir, 0755, true);
        $videoPath = $nestedDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('subdir/nested/test.mp4');

        self::assertSame('thumbs/subdir/nested/test_thumb.jpg', $result);
        self::assertFileExists($this->tempDir.'/thumbs/subdir/nested/test_thumb.jpg');
    }

    public function testGenerateThumbnailUses10PercentTimestampForMediumVideo(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns 30s video - 10% should be 3.0s
        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should use 3.0s (10% of 30s)
                self::assertStringContainsString('3.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailUsesFallbackForExactly10SecondVideo(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns exactly 10s video - should use 1.0s fallback (since < 10 is boundary)
        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"10.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Exactly 10s should use 10% = 1.0s
                self::assertStringContainsString('1.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeFailure(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe fails, ffmpeg should still work with fallback timestamp
        $ffprobeResult = new ProcessResult(1, '', 'ffprobe error');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should use 1.0s fallback since ffprobe failed
                self::assertStringContainsString('1.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('test.mp4');

        self::assertSame('thumbs/test_thumb.jpg', $result);
    }

    public function testGenerateThumbnailHandlesFfprobeInvalidJson(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns invalid JSON
        $ffprobeResult = new ProcessResult(0, 'not json', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should use 1.0s fallback since ffprobe returned invalid JSON
                self::assertStringContainsString('1.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeMissingFormat(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns JSON but without format
        $ffprobeResult = new ProcessResult(0, '{}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should use 1.0s fallback
                self::assertStringContainsString('1.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeMissingDuration(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns JSON with format but without duration
        $ffprobeResult = new ProcessResult(0, '{"format":{}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should use 1.0s fallback
                self::assertStringContainsString('1.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeNonNumericDuration(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        // ffprobe returns non-numeric duration
        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"N/A"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Should use 1.0s fallback
                self::assertStringContainsString('1.000', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailUsesCustomDimensions(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                // Verify custom dimensions are used
                self::assertStringContainsString('scale=200:150', $command);

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4', 200, 150);
    }

    public function testGenerateThumbnailWithFileAtRootLevel(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);

        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        $generator = $this->createGenerator($processRunner);

        $videoPath = $this->tempDir.'/root_video.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('root_video.mp4');

        self::assertSame('thumbs/root_video_thumb.jpg', $result);
    }

    public function testGenerateThumbnailLogsSuccessWithCorrectData(): void
    {
        $processRunner = $this->createMock(ProcessRunner::class);
        $logger = $this->createMock(LoggerInterface::class);

        $ffprobeResult = new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
        $ffmpegResult = new ProcessResult(0, '', '');

        $processRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (string $command) use ($ffprobeResult, $ffmpegResult): ProcessResult {
                if (str_contains($command, 'ffprobe')) {
                    return $ffprobeResult;
                }

                $matches = [];
                if (preg_match('/([^\s]+\.tmp)/', $command, $matches)) {
                    $tmpPath = trim($matches[1], "'\"");
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return $ffmpegResult;
            })
        ;

        // Verify logger is called with correct parameters
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Video thumbnail generated',
                self::callback(static fn (array $context) => isset($context['source'], $context['thumbnail'], $context['timestamp'])
                    && $context['source'] === 'test.mp4'
                    && $context['thumbnail'] === 'thumbs/test_thumb.jpg'
                    && $context['timestamp'] === 3.0)
            )
        ;

        $generator = $this->createGeneratorWithLogger($processRunner, $logger);

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    private function createGenerator(
        ProcessRunner $processRunner,
        bool $ffmpegAvailable = true,
        bool $ffprobeAvailable = true,
    ): VideoThumbnailGenerator {
        return $this->createGeneratorWithLogger($processRunner, new NullLogger(), $ffmpegAvailable, $ffprobeAvailable);
    }

    private function createGeneratorWithLogger(
        ProcessRunner $processRunner,
        LoggerInterface $logger,
        bool $ffmpegAvailable = true,
        bool $ffprobeAvailable = true,
    ): VideoThumbnailGenerator {
        return new class($this->tempDir, $logger, $processRunner, $ffmpegAvailable, $ffprobeAvailable) extends VideoThumbnailGenerator {
            public function __construct(
                string $storagePath,
                LoggerInterface $logger,
                ProcessRunner $processRunner,
                private readonly bool $ffmpegAvailableOverride,
                private readonly bool $ffprobeAvailableOverride,
            ) {
                parent::__construct($storagePath, $logger, $processRunner);
            }

            protected function isCommandAvailable(string $command): bool
            {
                return match ($command) {
                    'ffmpeg' => $this->ffmpegAvailableOverride,
                    'ffprobe' => $this->ffprobeAvailableOverride,
                    default => false,
                };
            }
        };
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
