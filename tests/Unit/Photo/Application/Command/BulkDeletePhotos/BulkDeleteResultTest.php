<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Command\BulkDeletePhotos;

use App\Photo\Application\Command\BulkDeletePhotos\BulkDeleteResult;
use App\Photo\Domain\Model\BulkOperationFailure;
use PHPUnit\Framework\TestCase;

final class BulkDeleteResultTest extends TestCase
{
    public function testTotalCountReturnsSum(): void
    {
        $result = new BulkDeleteResult(
            ['id1', 'id2'],
            [new BulkOperationFailure('id3', 'error')]
        );

        self::assertSame(3, $result->totalCount());
    }

    public function testDeletedCountReturnsDeletedCount(): void
    {
        $result = new BulkDeleteResult(
            ['id1', 'id2', 'id3'],
            []
        );

        self::assertSame(3, $result->deletedCount());
    }

    public function testFailedCountReturnsFailedCount(): void
    {
        $result = new BulkDeleteResult(
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
        $result = new BulkDeleteResult(
            [],
            [new BulkOperationFailure('id1', 'error')]
        );

        self::assertTrue($result->allFailed());
    }

    public function testAllFailedReturnsFalseWhenSomeSucceeded(): void
    {
        $result = new BulkDeleteResult(
            ['id1'],
            [new BulkOperationFailure('id2', 'error')]
        );

        self::assertFalse($result->allFailed());
    }

    public function testAllFailedReturnsFalseWhenEmpty(): void
    {
        $result = new BulkDeleteResult([], []);

        self::assertFalse($result->allFailed());
    }

    public function testAllSucceededReturnsTrueWhenAllSucceeded(): void
    {
        $result = new BulkDeleteResult(['id1', 'id2'], []);

        self::assertTrue($result->allSucceeded());
    }

    public function testAllSucceededReturnsFalseWhenSomeFailed(): void
    {
        $result = new BulkDeleteResult(
            ['id1'],
            [new BulkOperationFailure('id2', 'error')]
        );

        self::assertFalse($result->allSucceeded());
    }

    public function testAllSucceededReturnsFalseWhenEmpty(): void
    {
        $result = new BulkDeleteResult([], []);

        self::assertFalse($result->allSucceeded());
    }
}
