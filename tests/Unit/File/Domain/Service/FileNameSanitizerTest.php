<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\Domain\Service;

use App\File\Domain\Service\FileNameSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class FileNameSanitizerTest extends TestCase
{
    private FileNameSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new FileNameSanitizer();
    }

    public function testSanitizeAcceptsValidName(): void
    {
        $result = $this->sanitizer->sanitize('valid-filename.jpg');

        self::assertSame('valid-filename.jpg', $result);
    }

    public function testSanitizeReplacesForbiddenCharacters(): void
    {
        // 7 forbidden chars: < > : " | ? *
        $result = $this->sanitizer->sanitize('file<>:"|?*.jpg');

        self::assertSame('file_______.jpg', $result);
    }

    public function testSanitizeReplacesForwardSlash(): void
    {
        $result = $this->sanitizer->sanitize('folder/file.jpg');

        self::assertSame('folder_file.jpg', $result);
    }

    public function testSanitizeReplacesBackslash(): void
    {
        $result = $this->sanitizer->sanitize('folder\file.jpg');

        self::assertSame('folder_file.jpg', $result);
    }

    public function testSanitizeTrimsWhitespace(): void
    {
        $result = $this->sanitizer->sanitize('  filename.jpg  ');

        self::assertSame('filename.jpg', $result);
    }

    public function testSanitizeRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty or whitespace only');

        $this->sanitizer->sanitize('');
    }

    public function testSanitizeRejectsWhitespaceOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty or whitespace only');

        $this->sanitizer->sanitize('   ');
    }

    public function testSanitizeRejectsOnlyControlCharacters(): void
    {
        // Control characters are removed (not replaced), so this becomes empty
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name contains only forbidden characters');

        $this->sanitizer->sanitize("\x01\x02\x03\x1F");
    }

    public function testSanitizeHandlesReservedNameCON(): void
    {
        $result = $this->sanitizer->sanitize('CON.txt');

        self::assertSame('_CON.txt', $result);
    }

    public function testSanitizeHandlesReservedNamePRN(): void
    {
        $result = $this->sanitizer->sanitize('prn.log');

        self::assertSame('_prn.log', $result);
    }

    public function testSanitizeHandlesReservedNameNUL(): void
    {
        $result = $this->sanitizer->sanitize('NUL');

        self::assertSame('_NUL', $result);
    }

    public function testSanitizeHandlesReservedNameCOM1(): void
    {
        $result = $this->sanitizer->sanitize('COM1.txt');

        self::assertSame('_COM1.txt', $result);
    }

    public function testSanitizeHandlesReservedNameLPT1(): void
    {
        $result = $this->sanitizer->sanitize('LPT1.doc');

        self::assertSame('_LPT1.doc', $result);
    }

    public function testSanitizeDoesNotPrefixNonReservedNames(): void
    {
        $result = $this->sanitizer->sanitize('CONFERENCE.txt');

        self::assertSame('CONFERENCE.txt', $result);
    }

    public function testSanitizeTrimsTrailingDots(): void
    {
        $result = $this->sanitizer->sanitize('filename...');

        self::assertSame('filename', $result);
    }

    public function testSanitizeTrimsTrailingSpaces(): void
    {
        $result = $this->sanitizer->sanitize('filename   ');

        self::assertSame('filename', $result);
    }

    public function testSanitizeTrimsTrailingDotsAndSpaces(): void
    {
        $result = $this->sanitizer->sanitize('filename. . .');

        self::assertSame('filename', $result);
    }

    public function testSanitizeRejectsOnlyDotsAndSpaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot consist only of dots and spaces');

        $this->sanitizer->sanitize('. . .');
    }

    public function testSanitizeRemovesControlCharacters(): void
    {
        // \x00 is in FORBIDDEN_CHARS (replaced by _), \x1F is removed by preg_replace
        $result = $this->sanitizer->sanitize("file\x00name\x1F.jpg");

        self::assertSame('file_name.jpg', $result);
    }

    public function testSanitizeTruncatesLongNames(): void
    {
        $longName = str_repeat('a', 300).'.jpg';

        $result = $this->sanitizer->sanitize($longName);

        self::assertLessThanOrEqual(255, mb_strlen($result));
    }

    public function testSanitizePreservesExtensionWhenTruncating(): void
    {
        $longName = str_repeat('a', 300).'.jpg';

        $result = $this->sanitizer->sanitize($longName);

        // Should be truncated but still a valid name
        self::assertStringStartsWith('aaa', $result);
        self::assertLessThanOrEqual(255, mb_strlen($result));
    }

    public function testSanitizeHandlesUnicodeCharacters(): void
    {
        $result = $this->sanitizer->sanitize('fichier-été-2024.jpg');

        self::assertSame('fichier-été-2024.jpg', $result);
    }

    public function testSanitizeHandlesEmojiCharacters(): void
    {
        $result = $this->sanitizer->sanitize('photo-🏖️-plage.jpg');

        self::assertSame('photo-🏖️-plage.jpg', $result);
    }

    public function testSanitizeHandlesMultipleSpaces(): void
    {
        $result = $this->sanitizer->sanitize('my   document.pdf');

        self::assertSame('my   document.pdf', $result);
    }

    public function testSanitizeHandlesNameWithoutExtension(): void
    {
        $result = $this->sanitizer->sanitize('README');

        self::assertSame('README', $result);
    }

    public function testSanitizeHandlesMultipleDots(): void
    {
        $result = $this->sanitizer->sanitize('archive.tar.gz');

        self::assertSame('archive.tar.gz', $result);
    }
}
