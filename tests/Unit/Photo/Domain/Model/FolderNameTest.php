<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\FolderName;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class FolderNameTest extends TestCase
{
    public function testFromStringAcceptsValidName(): void
    {
        $name = FolderName::fromString('My Folder');

        self::assertSame('My Folder', $name->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $name = FolderName::fromString('  My Folder  ');

        self::assertSame('My Folder', $name->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Folder name cannot be empty');

        FolderName::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Folder name cannot be empty');

        FolderName::fromString('   ');
    }

    public function testFromStringRejectsTooLongName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Folder name cannot exceed 255 characters');

        FolderName::fromString(str_repeat('a', 256));
    }

    public function testFromStringAcceptsMaxLength(): void
    {
        $name = FolderName::fromString(str_repeat('a', 255));

        self::assertSame(255, \strlen($name->toString()));
    }

    public function testFromStringAcceptsExactly255Characters(): void
    {
        $input = str_repeat('x', 255);
        $name = FolderName::fromString($input);

        self::assertSame(255, \strlen($name->toString()));
    }

    public function testFromStringRejectsExactly256Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FolderName::fromString(str_repeat('x', 256));
    }

    public function testEqualsReturnsTrueForSameName(): void
    {
        $name1 = FolderName::fromString('My Folder');
        $name2 = FolderName::fromString('My Folder');

        self::assertTrue($name1->equals($name2));
    }

    public function testEqualsReturnsFalseForDifferentNames(): void
    {
        $name1 = FolderName::fromString('Folder A');
        $name2 = FolderName::fromString('Folder B');

        self::assertFalse($name1->equals($name2));
    }
}
