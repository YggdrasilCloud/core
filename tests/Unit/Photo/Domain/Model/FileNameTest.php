<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\FileName;
use PHPUnit\Framework\TestCase;

final class FileNameTest extends TestCase
{
    public function testFromStringAcceptsValidName(): void
    {
        $name = FileName::fromString('photo.jpg');

        $this->assertSame('photo.jpg', $name->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $name = FileName::fromString('  photo.jpg  ');

        $this->assertSame('photo.jpg', $name->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File name cannot be empty');

        FileName::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File name cannot be empty');

        FileName::fromString('   ');
    }

    public function testFromStringRejectsTooLongName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File name cannot exceed 255 characters');

        FileName::fromString(str_repeat('a', 256));
    }

    public function testFromStringAcceptsMaxLength(): void
    {
        $name = FileName::fromString(str_repeat('a', 255));

        $this->assertSame(255, strlen($name->toString()));
    }

    public function testEqualsReturnsTrueForSameName(): void
    {
        $name1 = FileName::fromString('photo.jpg');
        $name2 = FileName::fromString('photo.jpg');

        $this->assertTrue($name1->equals($name2));
    }

    public function testEqualsReturnsFalseForDifferentNames(): void
    {
        $name1 = FileName::fromString('photo.jpg');
        $name2 = FileName::fromString('image.png');

        $this->assertFalse($name1->equals($name2));
    }

    public function testExtensionReturnsCorrectExtension(): void
    {
        $name = FileName::fromString('photo.jpg');

        $this->assertSame('jpg', $name->extension());
    }

    public function testExtensionHandlesMultipleDots(): void
    {
        $name = FileName::fromString('photo.backup.tar.gz');

        $this->assertSame('gz', $name->extension());
    }

    public function testExtensionReturnsEmptyForNoExtension(): void
    {
        $name = FileName::fromString('photo');

        $this->assertSame('', $name->extension());
    }
}
