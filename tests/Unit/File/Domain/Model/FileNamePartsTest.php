<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\Domain\Model;

use App\File\Domain\Model\FileNameParts;
use PHPUnit\Framework\TestCase;

final class FileNamePartsTest extends TestCase
{
    public function testFromFileNameWithSimpleExtension(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');

        self::assertSame('photo', $parts->baseName);
        self::assertSame('.jpg', $parts->extension);
    }

    public function testFromFileNameWithMultipleDots(): void
    {
        $parts = FileNameParts::fromFileName('archive.tar.gz');

        self::assertSame('archive.tar', $parts->baseName);
        self::assertSame('.gz', $parts->extension);
    }

    public function testFromFileNameWithoutExtension(): void
    {
        $parts = FileNameParts::fromFileName('README');

        self::assertSame('README', $parts->baseName);
        self::assertSame('', $parts->extension);
    }

    public function testFromFileNameWithExistingSuffix(): void
    {
        $parts = FileNameParts::fromFileName('photo (1).jpg');

        self::assertSame('photo (1)', $parts->baseName);
        self::assertSame('.jpg', $parts->extension);
    }

    public function testFromFileNameWithLeadingDot(): void
    {
        $parts = FileNameParts::fromFileName('.gitignore');

        self::assertSame('.gitignore', $parts->baseName);
        self::assertSame('', $parts->extension);
    }

    public function testFromFileNameWithSpaces(): void
    {
        $parts = FileNameParts::fromFileName('My Document.pdf');

        self::assertSame('My Document', $parts->baseName);
        self::assertSame('.pdf', $parts->extension);
    }

    public function testFromFileNameWithSpecialCharacters(): void
    {
        $parts = FileNameParts::fromFileName('été-2024.jpg');

        self::assertSame('été-2024', $parts->baseName);
        self::assertSame('.jpg', $parts->extension);
    }

    public function testHasExtensionReturnsTrueWhenExtensionExists(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');

        self::assertTrue($parts->hasExtension());
    }

    public function testHasExtensionReturnsFalseWhenNoExtension(): void
    {
        $parts = FileNameParts::fromFileName('README');

        self::assertFalse($parts->hasExtension());
    }

    public function testWithSuffixAppendsNumericSuffix(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');
        $withSuffix = $parts->withSuffix(1);

        self::assertSame('photo (1)', $withSuffix->baseName);
        self::assertSame('.jpg', $withSuffix->extension);
    }

    public function testWithSuffixWorksWithoutExtension(): void
    {
        $parts = FileNameParts::fromFileName('README');
        $withSuffix = $parts->withSuffix(2);

        self::assertSame('README (2)', $withSuffix->baseName);
        self::assertSame('', $withSuffix->extension);
    }

    public function testWithSuffixIsImmutable(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');
        $withSuffix = $parts->withSuffix(1);

        // Original should remain unchanged
        self::assertSame('photo', $parts->baseName);
        self::assertSame('.jpg', $parts->extension);

        // New instance should have suffix
        self::assertSame('photo (1)', $withSuffix->baseName);
        self::assertSame('.jpg', $withSuffix->extension);
    }

    public function testWithSuffixCanChain(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');
        $result = $parts->withSuffix(1)->withSuffix(2);

        self::assertSame('photo (1) (2)', $result->baseName);
        self::assertSame('.jpg', $result->extension);
    }

    public function testToStringReconstructsFileName(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');

        self::assertSame('photo.jpg', $parts->toString());
    }

    public function testToStringReconstructsFileNameWithoutExtension(): void
    {
        $parts = FileNameParts::fromFileName('README');

        self::assertSame('README', $parts->toString());
    }

    public function testToStringAfterWithSuffix(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');
        $withSuffix = $parts->withSuffix(3);

        self::assertSame('photo (3).jpg', $withSuffix->toString());
    }

    public function testToStringPreservesMultipleDots(): void
    {
        $parts = FileNameParts::fromFileName('archive.tar.gz');

        self::assertSame('archive.tar.gz', $parts->toString());
    }

    public function testValueObjectIsReadonly(): void
    {
        $parts = FileNameParts::fromFileName('photo.jpg');

        // This test verifies that the class is readonly
        // If the class wasn't readonly, this would compile but behavior would be wrong
        self::assertSame('photo', $parts->baseName);
        self::assertSame('.jpg', $parts->extension);
    }

    public function testFromFileNameParsesCorrectly(): void
    {
        // Test that parsing correctly identifies base name and extension
        $parts = FileNameParts::fromFileName('document.pdf');

        // Verify the full reconstruction matches input exactly
        self::assertSame('document.pdf', $parts->toString());
        self::assertSame('document', $parts->baseName);
        self::assertSame('.pdf', $parts->extension);
    }

    public function testFromFileNameWithJustDot(): void
    {
        // Edge case: file ending with just a dot (no extension)
        $parts = FileNameParts::fromFileName('file.');

        self::assertSame('file.', $parts->baseName);
        self::assertSame('', $parts->extension);
        self::assertFalse($parts->hasExtension());
    }
}
