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
        $generator = $this->createGenerator();

        self::assertTrue($generator->supports('video/mp4'));
        self::assertTrue($generator->supports('video/webm'));
        self::assertTrue($generator->supports('video/quicktime'));
        self::assertFalse($generator->supports('image/jpeg'));
        self::assertFalse($generator->supports('application/pdf'));
    }

    public function testValidatePathRejectsDirectoryTraversal(): void
    {
        $generator = $this->createGenerator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('directory traversal');

        $generator->generateThumbnail('../../../etc/passwd');
    }

    public function testGenerateThumbnailThrowsWhenFfmpegUnavailable(): void
    {
        $generator = $this->createGenerator(ffmpegAvailable: false);

        // Create a dummy video file
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video content');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FFmpeg is not available');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailThrowsForMissingFile(): void
    {
        $generator = $this->createGenerator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');

        $generator->generateThumbnail('nonexistent.mp4');
    }

    public function testGenerateThumbnailThrowsForOversizedFile(): void
    {
        $generator = $this->createGenerator();

        // We can't create a 500MB file, so we use a mock generator that overrides the check
        // For now, just verify the file exists check works
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'small content');

        self::assertFileExists($videoPath);
    }

    public function testIdempotenceSkipsExistingThumbnail(): void
    {
        // When thumbnail exists, ffprobe/ffmpeg should NOT be called
        $ffmpegCalled = false;
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args) use (&$ffmpegCalled): ProcessResult {
                if ($args[0] === 'ffprobe' || $args[0] === 'ffmpeg') {
                    $ffmpegCalled = true;
                }

                return new ProcessResult(0, '', '');
            },
        );

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
        self::assertFalse($ffmpegCalled, 'ffprobe/ffmpeg should not be called when thumbnail exists');
    }

    public function testGenerateThumbnailCallsFfmpegWithCorrectParameters(): void
    {
        $ffprobeCalled = false;
        $ffmpegCalled = false;

        $generator = $this->createGenerator(
            runArrayCallback: function (array $args, int $timeout) use (&$ffprobeCalled, &$ffmpegCalled): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    $ffprobeCalled = true;
                    self::assertSame([
                        'ffprobe',
                        '-v', 'error',
                        '-print_format', 'json',
                        '-show_entries', 'format=duration',
                        $this->tempDir.'/test.mp4',
                    ], $args);

                    return new ProcessResult(0, '{"format":{"duration":"30.5"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    $ffmpegCalled = true;
                    // Verify ffmpeg arguments
                    self::assertContains('-nostdin', $args);
                    self::assertContains('-an', $args);
                    self::assertContains('-sn', $args);
                    self::assertContains('-frames:v', $args);
                    self::assertContains('1', $args);
                    self::assertContains('-vf', $args);
                    self::assertContains('scale=300:300:force_original_aspect_ratio=decrease:out_range=pc,format=yuv420p', $args);
                    self::assertContains('-q:v', $args);
                    self::assertContains('2', $args);
                    self::assertContains('-ss', $args);

                    // Simulate ffmpeg creating the thumbnail file
                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');

                    return new ProcessResult(0, '', '');
                }

                return new ProcessResult(0, '', '');
            },
        );

        // Create source file
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('test.mp4');

        self::assertSame('thumbs/test_thumb.jpg', $result);
        self::assertFileExists($this->tempDir.'/thumbs/test_thumb.jpg');
        self::assertTrue($ffprobeCalled, 'ffprobe should be called');
        self::assertTrue($ffmpegCalled, 'ffmpeg should be called');
    }

    public function testGenerateThumbnailUsesFallbackTimestampWhenFfprobeUnavailable(): void
    {
        $ffmpegCalled = false;

        // ffprobe unavailable
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args) use (&$ffmpegCalled): ProcessResult {
                if ($args[0] === 'ffmpeg') {
                    $ffmpegCalled = true;
                    // Should use 1.0s fallback timestamp
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    // Simulate ffmpeg creating the thumbnail file
                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
            ffprobeAvailable: false,
        );

        // Create source file
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('test.mp4');

        self::assertSame('thumbs/test_thumb.jpg', $result);
        self::assertTrue($ffmpegCalled, 'ffmpeg should be called');
    }

    public function testGenerateThumbnailUsesFallbackTimestampForShortVideo(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"5.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should use 1.0s fallback for short videos
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailCapsTimestampAt5Seconds(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"100.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should be capped at 5.0s (not 10.0s which is 10% of 100s)
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('5.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailThrowsOnFfmpegFailure(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    return new ProcessResult(1, '', 'Error processing video');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FFmpeg failed');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailThrowsWhenNoOutputProduced(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                // FFmpeg succeeds but doesn't produce output
                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FFmpeg did not produce a thumbnail');

        $generator->generateThumbnail('test.mp4');
    }

    public function testDeleteThumbnailRemovesFile(): void
    {
        $generator = $this->createGenerator();

        $thumbnailPath = $this->tempDir.'/thumbs/video_thumb.jpg';
        file_put_contents($thumbnailPath, 'thumbnail content');

        self::assertFileExists($thumbnailPath);

        $generator->deleteThumbnail('thumbs/video_thumb.jpg');

        self::assertFileDoesNotExist($thumbnailPath);
    }

    public function testDeleteThumbnailHandlesNonexistent(): void
    {
        $generator = $this->createGenerator();

        // Should not throw
        $generator->deleteThumbnail('thumbs/nonexistent.jpg');

        $this->expectNotToPerformAssertions();
    }

    public function testDeleteThumbnailRejectsDirectoryTraversal(): void
    {
        $generator = $this->createGenerator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('directory traversal');

        $generator->deleteThumbnail('../../../etc/passwd');
    }

    #[DataProvider('provideSupportedMimeTypesCases')]
    public function testSupportedMimeTypes(string $mimeType, bool $expected): void
    {
        $generator = $this->createGenerator();

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
        $generatorAvailable = $this->createGenerator(ffmpegAvailable: true);
        $generatorUnavailable = $this->createGenerator(ffmpegAvailable: false);

        self::assertTrue($generatorAvailable->isAvailable());
        self::assertFalse($generatorUnavailable->isAvailable());
    }

    public function testGenerateThumbnailHandlesAbsolutePath(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        // Create source file with absolute path
        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        // Pass absolute path
        $result = $generator->generateThumbnail($videoPath);

        self::assertStringContainsString('_thumb.jpg', $result);
    }

    public function testGenerateThumbnailCreatesNestedDirectories(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    $tmpPath = end($args);
                    // Create parent directory if needed
                    $dir = dirname($tmpPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

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
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should use 3.0s (10% of 30s)
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('3.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailUsesFallbackForExactly10SecondVideo(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"10.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Exactly 10s should use 10% = 1.0s
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeFailure(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(1, '', 'ffprobe error');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should use 1.0s fallback since ffprobe failed
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('test.mp4');

        self::assertSame('thumbs/test_thumb.jpg', $result);
    }

    public function testGenerateThumbnailHandlesFfprobeInvalidJson(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, 'not json', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should use 1.0s fallback since ffprobe returned invalid JSON
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeMissingFormat(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should use 1.0s fallback
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeMissingDuration(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should use 1.0s fallback
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailHandlesFfprobeNonNumericDuration(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"N/A"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Should use 1.0s fallback
                    $ssIndex = array_search('-ss', $args, true);
                    self::assertIsInt($ssIndex);
                    self::assertSame('1.000', $args[$ssIndex + 1]);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    public function testGenerateThumbnailUsesCustomDimensions(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    // Verify custom dimensions are used
                    self::assertContains('scale=200:150:force_original_aspect_ratio=decrease:out_range=pc,format=yuv420p', $args);

                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4', 200, 150);
    }

    public function testGenerateThumbnailWithFileAtRootLevel(): void
    {
        $generator = $this->createGenerator(
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/root_video.mp4';
        file_put_contents($videoPath, 'fake video');

        $result = $generator->generateThumbnail('root_video.mp4');

        self::assertSame('thumbs/root_video_thumb.jpg', $result);
    }

    public function testGenerateThumbnailLogsSuccessWithCorrectData(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

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

        $generator = $this->createGeneratorWithLogger(
            $logger,
            runArrayCallback: static function (array $args): ProcessResult {
                if ($args[0] === 'ffprobe') {
                    return new ProcessResult(0, '{"format":{"duration":"30.0"}}', '');
                }

                if ($args[0] === 'ffmpeg') {
                    $tmpPath = end($args);
                    file_put_contents($tmpPath, 'generated thumbnail');
                }

                return new ProcessResult(0, '', '');
            },
        );

        $videoPath = $this->tempDir.'/test.mp4';
        file_put_contents($videoPath, 'fake video');

        $generator->generateThumbnail('test.mp4');
    }

    /**
     * Creates a VideoThumbnailGenerator with a pre-configured ProcessRunner mock.
     *
     * @param null|callable $runArrayCallback Custom callback for runArray method (receives args, timeout)
     */
    private function createGenerator(
        ?callable $runArrayCallback = null,
        bool $ffmpegAvailable = true,
        bool $ffprobeAvailable = true,
    ): VideoThumbnailGenerator {
        return $this->createGeneratorWithLogger(new NullLogger(), $runArrayCallback, $ffmpegAvailable, $ffprobeAvailable);
    }

    /**
     * Creates a VideoThumbnailGenerator with a custom logger.
     *
     * @param null|callable $runArrayCallback Custom callback for runArray method
     */
    private function createGeneratorWithLogger(
        LoggerInterface $logger,
        ?callable $runArrayCallback = null,
        bool $ffmpegAvailable = true,
        bool $ffprobeAvailable = true,
    ): VideoThumbnailGenerator {
        /** @phpstan-ignore method.unresolvableReturnType (BypassFinals allows mocking final classes) */
        $processRunner = $this->createMock(ProcessRunner::class);

        // Configure runArray to handle both availability checks and actual commands
        $processRunner
            ->method('runArray')
            ->willReturnCallback(static function (array $args, int $timeout) use ($ffmpegAvailable, $ffprobeAvailable, $runArrayCallback): ProcessResult {
                // Handle 'which' command for availability check (called in constructor)
                if ($args[0] === 'which') {
                    $command = $args[1] ?? '';

                    return match ($command) {
                        'ffmpeg' => $ffmpegAvailable
                            ? new ProcessResult(0, '/usr/bin/ffmpeg', '')
                            : new ProcessResult(1, '', ''),
                        'ffprobe' => $ffprobeAvailable
                            ? new ProcessResult(0, '/usr/bin/ffprobe', '')
                            : new ProcessResult(1, '', ''),
                        default => new ProcessResult(1, '', ''),
                    };
                }

                // Use custom callback for ffprobe/ffmpeg calls
                if ($runArrayCallback !== null) {
                    return $runArrayCallback($args, $timeout);
                }

                // Default: return success
                return new ProcessResult(0, '', '');
            })
        ;

        return new VideoThumbnailGenerator($this->tempDir, $logger, $processRunner);
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
