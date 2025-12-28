<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\BulkOperationFailure;
use PHPUnit\Framework\TestCase;

final class BulkOperationFailureTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $failure = new BulkOperationFailure(
            '550e8400-e29b-41d4-a716-446655440001',
            'Photo not found'
        );

        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $failure->photoId);
        self::assertSame('Photo not found', $failure->reason);
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $failure = new BulkOperationFailure(
            '550e8400-e29b-41d4-a716-446655440001',
            'Delete operation failed'
        );

        $expected = [
            'photoId' => '550e8400-e29b-41d4-a716-446655440001',
            'reason' => 'Delete operation failed',
        ];

        self::assertSame($expected, $failure->toArray());
    }
}
