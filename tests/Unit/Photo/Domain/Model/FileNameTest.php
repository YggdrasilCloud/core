<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\FileName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function strlen;

final class FileNameTest extends TestCase
{
    public function testFromStringAcceptsValidName(): void
    {
        $name = FileName::fromString('photo.jpg');

        self::assertSame('photo.jpg', $name->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $name = FileName::fromString('  photo.jpg  ');

        self::assertSame('photo.jpg', $name->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File name cannot be empty');

        FileName::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File name cannot be empty');

        FileName::fromString('   ');
    }

    public function testFromStringRejectsTooLongName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File name cannot exceed 255 characters');

        FileName::fromString(str_repeat('a', 256));
    }

    public function testFromStringAcceptsMaxLength(): void
    {
        $name = FileName::fromString(str_repeat('a', 255));

        self::assertSame(255, strlen($name->toString()));
    }

    public function testEqualsReturnsTrueForSameName(): void
    {
        $name1 = FileName::fromString('photo.jpg');
        $name2 = FileName::fromString('photo.jpg');

        self::assertTrue($name1->equals($name2));
    }

    public function testEqualsReturnsFalseForDifferentNames(): void
    {
        $name1 = FileName::fromString('photo.jpg');
        $name2 = FileName::fromString('image.png');

        self::assertFalse($name1->equals($name2));
    }

    public function testExtensionReturnsCorrectExtension(): void
    {
        $name = FileName::fromString('photo.jpg');

        self::assertSame('jpg', $name->extension());
    }

    public function testExtensionHandlesMultipleDots(): void
    {
        $name = FileName::fromString('photo.backup.tar.gz');

        self::assertSame('gz', $name->extension());
    }

    public function testExtensionReturnsEmptyForNoExtension(): void
    {
        $name = FileName::fromString('photo');

        self::assertSame('', $name->extension());
    }

    public function testFromStringTrimIsNecessary(): void
    {
        // Verify that trim() is actually needed by testing with various whitespace
        $name = FileName::fromString("\t photo.jpg \n");

        self::assertSame('photo.jpg', $name->toString());
    }

    public function testFromStringBoundaryAt255Characters(): void
    {
        // Test exactly 255 passes, 256 fails - verifies > vs >= boundary
        $exactly255 = str_repeat('x', 255);
        $name = FileName::fromString($exactly255);

        self::assertSame(255, strlen($name->toString()));
    }

    public function testFromStringWithWhitespaceThenLengthCheck(): void
    {
        // After trimming, should pass length check
        // 255 chars + 2 spaces (trimmed) = valid
        $withSpaces = ' '.str_repeat('a', 255).' ';
        $name = FileName::fromString($withSpaces);

        self::assertSame(255, strlen($name->toString()));
    }
}
