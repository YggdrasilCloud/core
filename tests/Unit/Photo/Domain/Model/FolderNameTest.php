<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\FolderName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function strlen;

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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Folder name cannot be empty');

        FolderName::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Folder name cannot be empty');

        FolderName::fromString('   ');
    }

    public function testFromStringRejectsTooLongName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Folder name cannot exceed 255 characters');

        FolderName::fromString(str_repeat('a', 256));
    }

    public function testFromStringAcceptsMaxLength(): void
    {
        $name = FolderName::fromString(str_repeat('a', 255));

        self::assertSame(255, strlen($name->toString()));
    }

    public function testFromStringAcceptsExactly255Characters(): void
    {
        $input = str_repeat('x', 255);
        $name = FolderName::fromString($input);

        self::assertSame(255, strlen($name->toString()));
    }

    public function testFromStringRejectsExactly256Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

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

    public function testFromStringSanitizesForbiddenCharacters(): void
    {
        $name = FolderName::fromString('My/Folder\Test:Name*With?Forbidden"Chars<>|');

        // Forbidden characters are replaced with underscores, and trailing underscores are trimmed
        self::assertSame('My_Folder_Test_Name_With_Forbidden_Chars', $name->toString());
    }

    public function testFromStringKeepsAccents(): void
    {
        $name = FolderName::fromString('Été 2024 - Vacances à l\'étranger');

        self::assertSame('Été 2024 - Vacances à l\'étranger', $name->toString());
    }

    public function testFromStringNormalizesMultipleSpaces(): void
    {
        $name = FolderName::fromString('My    Folder   With     Spaces');

        self::assertSame('My Folder With Spaces', $name->toString());
    }

    public function testFromStringNormalizesMultipleUnderscores(): void
    {
        $name = FolderName::fromString('My____Folder');

        self::assertSame('My_Folder', $name->toString());
    }
}
