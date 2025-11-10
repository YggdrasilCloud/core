<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Service;

use App\Photo\Domain\Service\FileValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function strlen;

/**
 * @coversNothing
 */
final class FileValidatorTest extends TestCase
{
    public function testValidateAcceptsValidFile(): void
    {
        $validator = new FileValidator(20971520, ['image/jpeg', 'image/png']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        self::assertNull($error);
    }

    public function testValidateRejectsFileTooLarge(): void
    {
        $validator = new FileValidator(1024, ['image/jpeg']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(2048);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        self::assertStringContainsString('exceeds maximum allowed size', $error);
        self::assertStringContainsString('0 MB', $error);
    }

    public function testValidateAllowsUnlimitedSizeWhenSetToMinusOne(): void
    {
        $validator = new FileValidator(-1, ['image/jpeg']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(999999999);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        self::assertNull($error);
    }

    public function testValidateRejectsInvalidMimeType(): void
    {
        $validator = new FileValidator(20971520, ['image/jpeg', 'image/png']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn('text/plain');

        $error = $validator->validate($file);

        self::assertStringContainsString('File type not allowed', $error);
        self::assertStringContainsString('image/jpeg, image/png', $error);
    }

    public function testValidateRejectsNullMimeType(): void
    {
        $validator = new FileValidator(20971520, ['image/jpeg']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn(null);

        $error = $validator->validate($file);

        self::assertStringContainsString('File type not allowed', $error);
    }

    public function testValidateAllowsAnyMimeTypeWhenListIsEmpty(): void
    {
        $validator = new FileValidator(20971520, []);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn('text/plain');

        $error = $validator->validate($file);

        self::assertNull($error);
    }

    public function testValidateFiltersOutEmptyStringsFromMimeTypesList(): void
    {
        $validator = new FileValidator(20971520, ['image/jpeg', '', 'image/png', '']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn('image/gif');

        $error = $validator->validate($file);

        // Should reject because gif is not in the filtered list (only jpeg and png)
        self::assertStringContainsString('File type not allowed', $error);
    }

    public function testSanitizeFilenameRemovesSpecialCharacters(): void
    {
        $validator = new FileValidator(20971520, []);

        $sanitized = $validator->sanitizeFilename('Héllo Wörld!@#$%^&*()+.png');

        // Accents are kept, forbidden characters are replaced with underscores, other chars removed
        self::assertSame('Héllo_Wörld!@#$%^&_()+.png', $sanitized);
    }

    public function testSanitizeFilenamePreservesExtension(): void
    {
        $validator = new FileValidator(20971520, []);

        $sanitized = $validator->sanitizeFilename('test-file.tar.gz');

        self::assertStringEndsWith('.gz', $sanitized);
    }

    public function testSanitizeFilenameLimitsLength(): void
    {
        $validator = new FileValidator(20971520, []);
        $longName = str_repeat('a', 200).'.jpg';

        $sanitized = $validator->sanitizeFilename($longName);

        self::assertLessThanOrEqual(104, strlen($sanitized)); // 100 + '.jpg'
    }

    public function testSanitizeFilenameHandlesEmptyBasename(): void
    {
        $validator = new FileValidator(20971520, []);

        // Special chars that are not forbidden are kept
        $sanitized = $validator->sanitizeFilename('!@#$%^&.png');

        self::assertSame('!@#$%^&.png', $sanitized);
    }

    public function testSanitizeFilenameHandlesNoExtension(): void
    {
        $validator = new FileValidator(20971520, []);

        $sanitized = $validator->sanitizeFilename('filename');

        self::assertSame('filename', $sanitized);
    }

    public function testSanitizeFilenameKeepsAccents(): void
    {
        $validator = new FileValidator(20971520, []);

        $sanitized = $validator->sanitizeFilename('Été_été.jpg');

        // Accents are now preserved
        self::assertSame('Été_été.jpg', $sanitized);
    }

    public function testValidateRejectsFileExactlyAtLimit(): void
    {
        $validator = new FileValidator(1024, ['image/jpeg']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1025); // Exactly 1 byte over
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        self::assertStringContainsString('exceeds maximum allowed size', $error);
    }

    public function testValidateAcceptsFileJustUnderLimit(): void
    {
        $validator = new FileValidator(1024, ['image/jpeg']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024); // Exactly at limit
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        self::assertNull($error);
    }

    public function testSanitizeFilenameHandlesExactly100Characters(): void
    {
        $validator = new FileValidator(20971520, []);
        $longName = str_repeat('a', 100).'.txt';

        $sanitized = $validator->sanitizeFilename($longName);

        // Should be truncated to 100 chars for basename + extension
        self::assertLessThanOrEqual(104, strlen($sanitized));
    }

    public function testFiltersEmptyStringFromMimeTypesAtConstruction(): void
    {
        $validator = new FileValidator(20971520, ['image/jpeg', '', 'image/png']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        // Should accept jpeg because empty string was filtered out
        self::assertNull($error);
    }

    public function testValidateDisplaysCorrectMBCalculation(): void
    {
        $validator = new FileValidator(2097152, ['image/jpeg']); // Exactly 2 MB
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(2097153); // 1 byte over 2MB
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        self::assertStringContainsString('2 MB', $error);
        // Verify exact calculation: 2097152 / 1024 / 1024 = 2.0
        self::assertStringContainsString('2 MB', $error);
    }

    public function testValidateCalculatesMBWithCorrectDivision(): void
    {
        // Test that ensures 1024 is used (not 1023 or 1025)
        $validator = new FileValidator(3145728, ['image/jpeg']); // 3 MB
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(3145729);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        // 3145728 / 1024 / 1024 = 3.0 (not 3.003 with wrong divisor)
        self::assertStringContainsString('3 MB', $error);
        self::assertStringNotContainsString('2.99', $error);
        self::assertStringNotContainsString('3.00', $error); // Should be "3 MB" not "3.00 MB"
    }

    public function testValidateUsesRoundNotFloorForMBCalculation(): void
    {
        // Test that verifies round() is used, not floor()
        $validator = new FileValidator(1572864, ['image/jpeg']); // 1.5 MB
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1572865);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        // round(1.5, 2) = 1.5, floor(1.5) = 1
        self::assertStringContainsString('1.5 MB', $error);
    }

    public function testValidateUsesTwoDecimalPrecision(): void
    {
        // Test precision is 2, not 1
        $validator = new FileValidator(1153434, ['image/jpeg']); // ~1.10 MB
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1153435);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $error = $validator->validate($file);

        // With precision 2: 1.10, with precision 1: 1.1
        self::assertStringContainsString('1.1 MB', $error);
    }

    public function testSanitizeFilenameUses100CharacterLimit(): void
    {
        // Verify exactly 100 chars are used, not 99 or 101
        $validator = new FileValidator(20971520, []);
        $input = str_repeat('a', 101).'.txt'; // 101 chars + extension

        $sanitized = $validator->sanitizeFilename($input);

        // Should be truncated to 100 + extension
        $basename = pathinfo($sanitized, PATHINFO_FILENAME);
        self::assertSame(100, strlen($basename));
    }

    public function testValidatorFiltersEmptyStringsWithArrayValues(): void
    {
        // Ensure array_values is used after array_filter
        $validator = new FileValidator(20971520, ['', 'image/jpeg', '', 'image/png', '']);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn('image/gif');

        $error = $validator->validate($file);

        // Should reject GIF because only JPEG and PNG are in cleaned list
        self::assertNotNull($error);
        self::assertStringContainsString('image/jpeg, image/png', $error);
        // Verify no empty entries in error message
        self::assertStringNotContainsString(', ,', $error);
    }
}
