<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Service;

use App\Photo\Domain\Service\ThumbnailGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ThumbnailGeneratorTest extends TestCase
{
    private string $tempDir;
    private ThumbnailGenerator $generator;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/thumbnail_test_'.uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->generator = new ThumbnailGenerator($this->tempDir, $this->logger);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGetMethodReturnsValidMethod(): void
    {
        $method = $this->generator->getMethod();

        self::assertContains($method, ['vipsthumbnail (libvips)', 'PHP GD']);
    }

    public function testGenerateThumbnailRejectsDirectoryTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot contain directory traversal (..)');

        $this->generator->generateThumbnail('../etc/passwd');
    }

    public function testGenerateThumbnailRejectsPathWithDoubleDots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot contain directory traversal (..)');

        $this->generator->generateThumbnail('photos/../etc/passwd');
    }

    public function testDeleteThumbnailRejectsDirectoryTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot contain directory traversal (..)');

        $this->generator->deleteThumbnail('../etc/passwd');
    }

    public function testDeleteThumbnailDoesNothingWhenFileDoesNotExist(): void
    {
        // Should not throw exception
        $this->generator->deleteThumbnail('thumbs/non-existent.jpg');

        $this->expectNotToPerformAssertions();
    }

    public function testDeleteThumbnailRemovesExistingFile(): void
    {
        $thumbnailPath = 'thumbs/test_thumb.jpg';
        $fullPath = $this->tempDir.'/'.$thumbnailPath;

        // Create directory and file
        mkdir(dirname($fullPath), 0755, true);
        touch($fullPath);

        self::assertFileExists($fullPath);

        $this->generator->deleteThumbnail($thumbnailPath);

        self::assertFileDoesNotExist($fullPath);
    }

    public function testGenerateThumbnailCreatesJpegThumbnail(): void
    {
        // Create a simple test JPEG image
        $sourceDir = $this->tempDir.'/photos';
        mkdir($sourceDir, 0755, true);
        $sourcePath = $sourceDir.'/test.jpg';

        $image = imagecreatetruecolor(800, 600);
        self::assertNotFalse($image);
        imagejpeg($image, $sourcePath);
        imagedestroy($image);

        $thumbnailPath = $this->generator->generateThumbnail('photos/test.jpg', 300, 300);

        self::assertStringStartsWith('thumbs/', $thumbnailPath);
        self::assertFileExists($this->tempDir.'/'.$thumbnailPath);

        // Verify thumbnail dimensions
        $thumbInfo = getimagesize($this->tempDir.'/'.$thumbnailPath);
        self::assertNotFalse($thumbInfo);
        [$thumbWidth, $thumbHeight] = $thumbInfo;

        // Should be scaled down maintaining aspect ratio (800x600 -> 300x225)
        self::assertLessThanOrEqual(300, $thumbWidth);
        self::assertLessThanOrEqual(300, $thumbHeight);
    }

    public function testGenerateThumbnailCreatesPngThumbnail(): void
    {
        // Create a simple test PNG image
        $sourceDir = $this->tempDir.'/photos';
        mkdir($sourceDir, 0755, true);
        $sourcePath = $sourceDir.'/test.png';

        $image = imagecreatetruecolor(400, 400);
        self::assertNotFalse($image);
        imagepng($image, $sourcePath);
        imagedestroy($image);

        $thumbnailPath = $this->generator->generateThumbnail('photos/test.png', 200, 200);

        self::assertStringStartsWith('thumbs/', $thumbnailPath);
        self::assertFileExists($this->tempDir.'/'.$thumbnailPath);

        // Verify thumbnail dimensions
        $thumbInfo = getimagesize($this->tempDir.'/'.$thumbnailPath);
        self::assertNotFalse($thumbInfo);
        [$thumbWidth, $thumbHeight] = $thumbInfo;

        self::assertLessThanOrEqual(200, $thumbWidth);
        self::assertLessThanOrEqual(200, $thumbHeight);
    }

    public function testGenerateThumbnailMaintainsAspectRatio(): void
    {
        // Create a wide image (2:1 ratio)
        $sourceDir = $this->tempDir.'/photos';
        mkdir($sourceDir, 0755, true);
        $sourcePath = $sourceDir.'/wide.jpg';

        $image = imagecreatetruecolor(1000, 500);
        self::assertNotFalse($image);
        imagejpeg($image, $sourcePath);
        imagedestroy($image);

        $thumbnailPath = $this->generator->generateThumbnail('photos/wide.jpg', 300, 300);

        $thumbInfo = getimagesize($this->tempDir.'/'.$thumbnailPath);
        self::assertNotFalse($thumbInfo);
        [$thumbWidth, $thumbHeight] = $thumbInfo;

        // Should maintain 2:1 aspect ratio (300x150)
        self::assertSame(300, $thumbWidth);
        self::assertSame(150, $thumbHeight);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
