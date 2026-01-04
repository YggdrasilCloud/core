<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Model\DeletedContent;
use PHPUnit\Framework\TestCase;

final class DeletedContentTest extends TestCase
{
    public function testCanBeCreatedWithCounts(): void
    {
        $content = new DeletedContent(5, 3);

        self::assertSame(5, $content->photosDeleted);
        self::assertSame(3, $content->subfoldersDeleted);
    }

    public function testEmptyCreatesZeroCounts(): void
    {
        $content = DeletedContent::empty();

        self::assertSame(0, $content->photosDeleted);
        self::assertSame(0, $content->subfoldersDeleted);
    }

    public function testMergeAddsCounts(): void
    {
        $content1 = new DeletedContent(5, 2);
        $content2 = new DeletedContent(3, 1);

        $merged = $content1->merge($content2);

        self::assertSame(8, $merged->photosDeleted);
        self::assertSame(3, $merged->subfoldersDeleted);
    }

    public function testMergeWithEmptyReturnsSameValues(): void
    {
        $content = new DeletedContent(5, 2);
        $empty = DeletedContent::empty();

        $merged = $content->merge($empty);

        self::assertSame(5, $merged->photosDeleted);
        self::assertSame(2, $merged->subfoldersDeleted);
    }

    public function testMergeIsImmutable(): void
    {
        $content1 = new DeletedContent(5, 2);
        $content2 = new DeletedContent(3, 1);

        $merged = $content1->merge($content2);

        // Original objects unchanged
        self::assertSame(5, $content1->photosDeleted);
        self::assertSame(3, $content2->photosDeleted);

        // Merged is a new object
        self::assertNotSame($content1, $merged);
        self::assertNotSame($content2, $merged);
    }
}
