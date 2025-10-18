<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\PhotoId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class PhotoIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = PhotoId::generate();

        self::assertTrue(Uuid::isValid($id->toString()));
    }

    public function testFromStringAcceptsValidUuid(): void
    {
        $uuidString = '0199d0b2-31cf-72ef-b43c-7d5563a01cdf';
        $id = PhotoId::fromString($uuidString);

        self::assertSame($uuidString, $id->toString());
    }

    public function testFromStringRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PhotoId: invalid-uuid');

        PhotoId::fromString('invalid-uuid');
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $uuidString = '0199d0b2-31cf-72ef-b43c-7d5563a01cdf';
        $id1 = PhotoId::fromString($uuidString);
        $id2 = PhotoId::fromString($uuidString);

        self::assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $id1 = PhotoId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $id2 = PhotoId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01ce0');

        self::assertFalse($id1->equals($id2));
    }

    public function testGeneratedIdsAreUnique(): void
    {
        $id1 = PhotoId::generate();
        $id2 = PhotoId::generate();

        self::assertFalse($id1->equals($id2));
    }
}
