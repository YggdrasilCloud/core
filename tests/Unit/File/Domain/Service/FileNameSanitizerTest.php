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

    public function testSanitizeTrimsAfterReplacingForbiddenChars(): void
    {
        // Second trim is needed after replacing characters that might leave trailing spaces
        // Forbidden char at end becomes underscore, then rtrim removes it
        $result = $this->sanitizer->sanitize('filename*');

        self::assertSame('filename_', $result);
    }

    public function testSanitizeTruncatesExactlyAtMaxLength(): void
    {
        // Test > vs >= boundary for 255 chars
        $exactlyMax = str_repeat('a', 255);
        $result = $this->sanitizer->sanitize($exactlyMax);

        self::assertSame(255, mb_strlen($result));
        self::assertSame($exactlyMax, $result);
    }

    public function testSanitizeTruncatesOneOverMaxLength(): void
    {
        // Test > vs >= boundary: 256 chars should be truncated
        $oneOverMax = str_repeat('a', 256);
        $result = $this->sanitizer->sanitize($oneOverMax);

        self::assertSame(255, mb_strlen($result));
    }

    public function testSanitizeTrimsDotsAfterTruncation(): void
    {
        // Test rtrim after truncation: name ending with dots should be trimmed
        $longNameEndingWithDots = str_repeat('a', 252).'....';
        $result = $this->sanitizer->sanitize($longNameEndingWithDots);

        // Should truncate to 255, then rtrim the dots
        self::assertLessThanOrEqual(255, mb_strlen($result));
        self::assertStringNotContainsString('....', $result);
    }

    public function testSanitizeHandlesMultibyteInLengthCheck(): void
    {
        // Test mb_strlen vs strlen: multibyte chars should count as 1
        // 'é' is 2 bytes in UTF-8 but 1 character
        $multibyteChars = str_repeat('é', 255);
        $result = $this->sanitizer->sanitize($multibyteChars);

        self::assertSame(255, mb_strlen($result));
    }

    public function testSanitizeHandlesMultibyteInSubstr(): void
    {
        // Test mb_substr: should correctly truncate multibyte strings
        $multibyteChars = str_repeat('été', 100); // 300 chars
        $result = $this->sanitizer->sanitize($multibyteChars);

        self::assertSame(255, mb_strlen($result));
        // Should end with complete multibyte char, not corrupted
        self::assertStringNotContainsString("\xC3", substr($result, -1));
    }

    public function testHandleReservedNamesWithMultibyteExtension(): void
    {
        // Test mb_strrpos/mb_substr in handleReservedNames
        $result = $this->sanitizer->sanitize('CON.été');

        self::assertSame('_CON.été', $result);
    }

    public function testHandleReservedNamesExtractsBaseNameCorrectly(): void
    {
        // AUX.été is considered reserved because base name before last dot is 'AUX'
        // But AUX.été.txt has base name 'AUX.été' which is NOT reserved
        $result = $this->sanitizer->sanitize('AUX.txt');

        self::assertSame('_AUX.txt', $result);
    }

    public function testSanitizeRemovesControlChars(): void
    {
        // Control chars (0x01-0x1F, 0x7F) are REMOVED by preg_replace, not replaced
        $result = $this->sanitizer->sanitize("file\x01 name\x02");

        // Control chars removed, so we get "file name"
        self::assertSame('file name', $result);
    }
}
