<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\MimeType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function strlen;

final class MimeTypeTest extends TestCase
{
    public function testFromStringAcceptsValidMimeType(): void
    {
        $mimeType = MimeType::fromString('image/jpeg');

        self::assertSame('image/jpeg', $mimeType->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $mimeType = MimeType::fromString('  image/png  ');

        self::assertSame('image/png', $mimeType->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MIME type cannot be empty');

        MimeType::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MIME type cannot be empty');

        MimeType::fromString('   ');
    }

    public function testFromStringRejectsInvalidFormatWithoutSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid MIME type format: imagejpeg');

        MimeType::fromString('imagejpeg');
    }

    public function testFromStringRejectsTooLongMimeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MIME type too long');

        MimeType::fromString(str_repeat('a', 128).'/'.str_repeat('b', 128));
    }

    public function testFromStringAcceptsMaxLength(): void
    {
        // 254 chars total (127 + '/' + 126)
        $longType = str_repeat('a', 127).'/'.str_repeat('b', 126);
        $mimeType = MimeType::fromString($longType);

        self::assertSame(254, strlen($mimeType->toString()));
    }

    public function testEqualsReturnsTrueForSameMimeType(): void
    {
        $mimeType1 = MimeType::fromString('image/jpeg');
        $mimeType2 = MimeType::fromString('image/jpeg');

        self::assertTrue($mimeType1->equals($mimeType2));
    }

    public function testEqualsReturnsTrueForSameMimeTypeAfterTrim(): void
    {
        $mimeType1 = MimeType::fromString('image/jpeg  ');
        $mimeType2 = MimeType::fromString('  image/jpeg');

        self::assertTrue($mimeType1->equals($mimeType2));
    }

    public function testEqualsReturnsFalseForDifferentMimeTypes(): void
    {
        $mimeType1 = MimeType::fromString('image/jpeg');
        $mimeType2 = MimeType::fromString('image/png');

        self::assertFalse($mimeType1->equals($mimeType2));
    }

    public function testFromStringAcceptsVariousMimeTypes(): void
    {
        $testCases = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'application/pdf',
            'text/plain',
            'application/json',
        ];

        foreach ($testCases as $mimeTypeString) {
            $mimeType = MimeType::fromString($mimeTypeString);
            self::assertSame($mimeTypeString, $mimeType->toString());
        }
    }
}
