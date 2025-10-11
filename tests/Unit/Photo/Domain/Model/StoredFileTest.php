<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\StoredFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class StoredFileTest extends TestCase
{
    public function testCreateAcceptsValidParameters(): void
    {
        $file = StoredFile::create('2025/10/11/photo.jpg', 'image/jpeg', 1024);

        self::assertSame('2025/10/11/photo.jpg', $file->storagePath());
        self::assertSame('image/jpeg', $file->mimeType());
        self::assertSame(1024, $file->sizeInBytes());
    }

    public function testCreateRejectsEmptyStoragePath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage path cannot be empty');

        StoredFile::create('', 'image/jpeg', 1024);
    }

    public function testCreateRejectsWhitespaceOnlyStoragePath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage path cannot be empty');

        StoredFile::create('   ', 'image/jpeg', 1024);
    }

    public function testCreateRejectsEmptyMimeType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mime type cannot be empty');

        StoredFile::create('photo.jpg', '', 1024);
    }

    public function testCreateRejectsWhitespaceOnlyMimeType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mime type cannot be empty');

        StoredFile::create('photo.jpg', '   ', 1024);
    }

    public function testCreateRejectsNonImageMimeType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mime type for photo: text/plain');

        StoredFile::create('photo.jpg', 'text/plain', 1024);
    }

    public function testCreateRejectsNegativeSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size cannot be negative');

        StoredFile::create('photo.jpg', 'image/jpeg', -1);
    }

    public function testCreateAcceptsZeroSize(): void
    {
        $file = StoredFile::create('photo.jpg', 'image/jpeg', 0);

        self::assertSame(0, $file->sizeInBytes());
    }

    #[DataProvider('provideCreateAcceptsVariousImageMimeTypesCases')]
    public function testCreateAcceptsVariousImageMimeTypes(string $mimeType): void
    {
        $file = StoredFile::create('photo.jpg', $mimeType, 1024);

        self::assertSame($mimeType, $file->mimeType());
    }

    public static function provideCreateAcceptsVariousImageMimeTypesCases(): iterable
    {
        yield 'JPEG' => ['image/jpeg'];

        yield 'PNG' => ['image/png'];

        yield 'GIF' => ['image/gif'];

        yield 'WebP' => ['image/webp'];

        yield 'SVG' => ['image/svg+xml'];

        yield 'BMP' => ['image/bmp'];
    }
}
