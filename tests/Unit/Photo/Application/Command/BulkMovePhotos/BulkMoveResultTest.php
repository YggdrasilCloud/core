<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Command\BulkMovePhotos;

use App\Photo\Application\Command\BulkMovePhotos\BulkMoveResult;
use App\Photo\Domain\Model\BulkOperationFailure;
use PHPUnit\Framework\TestCase;

final class BulkMoveResultTest extends TestCase
{
    public function testTotalCountReturnsSum(): void
    {
        $result = new BulkMoveResult(
            ['id1', 'id2'],
            [new BulkOperationFailure('id3', 'error')]
        );

        self::assertSame(3, $result->totalCount());
    }

    public function testMovedCountReturnsMovedCount(): void
    {
        $result = new BulkMoveResult(
            ['id1', 'id2', 'id3'],
            []
        );

        self::assertSame(3, $result->movedCount());
    }

    public function testFailedCountReturnsFailedCount(): void
    {
        $result = new BulkMoveResult(
            [],
            [
                new BulkOperationFailure('id1', 'error1'),
                new BulkOperationFailure('id2', 'error2'),
            ]
        );

        self::assertSame(2, $result->failedCount());
    }

    public function testAllFailedReturnsTrueWhenAllFailed(): void
    {
        $result = new BulkMoveResult(
            [],
            [new BulkOperationFailure('id1', 'error')]
        );

        self::assertTrue($result->allFailed());
    }

    public function testAllFailedReturnsFalseWhenSomeSucceeded(): void
    {
        $result = new BulkMoveResult(
            ['id1'],
            [new BulkOperationFailure('id2', 'error')]
        );

        self::assertFalse($result->allFailed());
    }

    public function testAllSucceededReturnsTrueWhenAllSucceeded(): void
    {
        $result = new BulkMoveResult(['id1', 'id2'], []);

        self::assertTrue($result->allSucceeded());
    }

    public function testAllSucceededReturnsFalseWhenSomeFailed(): void
    {
        $result = new BulkMoveResult(
            ['id1'],
            [new BulkOperationFailure('id2', 'error')]
        );

        self::assertFalse($result->allSucceeded());
    }
}
