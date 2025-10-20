<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\Infrastructure\Storage\Adapter;

use App\File\Domain\Model\StoredObject;
use App\File\Infrastructure\Storage\Adapter\LocalStorage;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class LocalStorageTest extends TestCase
{
    private string $tempDir;
    private LocalStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/yggdrasil-storage-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->storage = new LocalStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function itSavesFileFromStream(): void
    {
        $content = 'test file content';
        $stream = $this->createStreamFromString($content);
        $key = 'files/test/document.txt';

        $result = $this->storage->save($stream, $key, 'text/plain', strlen($content));

        self::assertInstanceOf(StoredObject::class, $result);
        self::assertSame($key, $result->key);
        self::assertSame('local', $result->adapter);
        self::assertInstanceOf(DateTimeImmutable::class, $result->storedAt);

        // Verify file exists on disk
        $fullPath = $this->tempDir.'/'.$key;
        self::assertFileExists($fullPath);
        self::assertSame($content, file_get_contents($fullPath));
    }

    #[Test]
    public function itCreatesNestedDirectoriesAutomatically(): void
    {
        $stream = $this->createStreamFromString('content');
        $key = 'deep/nested/path/to/file.txt';

        $this->storage->save($stream, $key, 'text/plain', 7);

        $fullPath = $this->tempDir.'/'.$key;
        self::assertFileExists($fullPath);
    }

    #[Test]
    public function itAcceptsUnknownSizeWithNegativeOne(): void
    {
        $content = 'file with unknown size';
        $stream = $this->createStreamFromString($content);
        $key = 'files/test/unknown-size.txt';

        // -1 means unknown size, should not throw exception
        $result = $this->storage->save($stream, $key, 'text/plain', -1);

        self::assertSame($key, $result->key);
        self::assertFileExists($this->tempDir.'/'.$key);
    }

    #[Test]
    public function itReadsFileAsStream(): void
    {
        $content = 'stored file content';
        $key = 'files/test/read.txt';

        // Save file first
        $saveStream = $this->createStreamFromString($content);
        $this->storage->save($saveStream, $key, 'text/plain', strlen($content));

        // Read it back
        $readStream = $this->storage->readStream($key);

        $readContent = stream_get_contents($readStream);
        self::assertSame($content, $readContent);

        fclose($readStream);
    }

    #[Test]
    public function itDeletesExistingFile(): void
    {
        $key = 'files/test/delete.txt';

        // Save file first
        $stream = $this->createStreamFromString('content');
        $this->storage->save($stream, $key, 'text/plain', 7);

        self::assertTrue($this->storage->exists($key));

        // Delete it
        $this->storage->delete($key);

        self::assertFalse($this->storage->exists($key));
    }

    #[Test]
    public function itDeletesNonExistentFileIdempotently(): void
    {
        $key = 'files/test/nonexistent.txt';

        // Should not throw exception
        $this->storage->delete($key);

        self::assertFalse($this->storage->exists($key));
    }

    #[Test]
    public function itChecksFileExistence(): void
    {
        $key = 'files/test/exists.txt';

        self::assertFalse($this->storage->exists($key));

        // Save file
        $stream = $this->createStreamFromString('content');
        $this->storage->save($stream, $key, 'text/plain', 7);

        self::assertTrue($this->storage->exists($key));
    }

    #[Test]
    public function itReturnsNullForUrlMethod(): void
    {
        $key = 'files/test/file.txt';

        // Local storage has no public URLs
        $url = $this->storage->url($key);

        self::assertNull($url);
    }

    #[Test]
    public function itThrowsExceptionForInvalidStream(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream must be a valid resource');

        // @phpstan-ignore argument.type
        $this->storage->save('not a stream', 'files/test/file.txt', 'text/plain', 100);
    }

    #[Test]
    public function itThrowsExceptionWhenReadingNonExistentFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $this->storage->readStream('files/nonexistent/file.txt');
    }

    #[Test]
    public function itThrowsExceptionForDirectoryTraversalAttack(): void
    {
        $stream = $this->createStreamFromString('content');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid key (directory traversal)');

        $this->storage->save($stream, 'files/../../../etc/passwd', 'text/plain', 7);
    }

    #[Test]
    public function itThrowsExceptionForControlCharactersInKey(): void
    {
        $stream = $this->createStreamFromString('content');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid key (control characters not allowed)');

        // Try with null byte (common injection attack)
        $this->storage->save($stream, "files/test\x00malicious.txt", 'text/plain', 7);
    }

    #[Test]
    public function itThrowsExceptionWhenSizeMismatch(): void
    {
        $content = 'test content';
        $stream = $this->createStreamFromString($content);
        $wrongSize = 999;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File size mismatch');

        $this->storage->save($stream, 'files/test/file.txt', 'text/plain', $wrongSize);
    }

    #[Test]
    public function itHandlesBinaryFiles(): void
    {
        // Create a small binary content (PNG header)
        $binaryContent = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR";
        $stream = $this->createStreamFromString($binaryContent);
        $key = 'files/test/image.png';

        $this->storage->save($stream, $key, 'image/png', strlen($binaryContent));

        // Read back
        $readStream = $this->storage->readStream($key);
        $readContent = stream_get_contents($readStream);
        fclose($readStream);

        self::assertSame($binaryContent, $readContent);
    }

    #[Test]
    public function itHandlesLargeFileStreams(): void
    {
        // Simulate a 1MB file
        $size = 1024 * 1024;
        $content = str_repeat('A', $size);
        $stream = $this->createStreamFromString($content);
        $key = 'files/test/large.bin';

        $result = $this->storage->save($stream, $key, 'application/octet-stream', $size);

        self::assertSame($key, $result->key);

        // Verify file size on disk
        $fullPath = $this->tempDir.'/'.$key;
        self::assertSame($size, filesize($fullPath));
    }

    #[Test]
    public function itNormalizesKeyWithLeadingSlash(): void
    {
        $stream = $this->createStreamFromString('content');
        $key = '/files/test/normalized.txt';

        $this->storage->save($stream, $key, 'text/plain', 7);

        // Should save without the leading slash
        $fullPath = $this->tempDir.'/files/test/normalized.txt';
        self::assertFileExists($fullPath);
    }

    #[Test]
    public function itThrowsExceptionForEmptyBasePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Base path cannot be empty');

        new LocalStorage('');
    }

    /**
     * Create a stream from string content.
     *
     * @return resource
     */
    private function createStreamFromString(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Failed to create memory stream');
        }

        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * Recursively remove directory and its contents.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir.'/'.$file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
