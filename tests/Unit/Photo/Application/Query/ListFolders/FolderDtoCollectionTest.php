<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Query\ListFolders;

use App\Photo\Application\Query\ListFolders\FolderDto;
use App\Photo\Application\Query\ListFolders\FolderDtoCollection;
use PHPUnit\Framework\TestCase;

final class FolderDtoCollectionTest extends TestCase
{
    public function testFromArrayCreatesCollection(): void
    {
        $folders = [
            new FolderDto('id1', 'Folder 1', '2025-01-01T00:00:00+00:00', 'parent1'),
            new FolderDto('id2', 'Folder 2', '2025-01-02T00:00:00+00:00', null),
        ];

        $collection = FolderDtoCollection::fromArray($folders);

        self::assertSame($folders, $collection->toArray());
        self::assertCount(2, $collection);
    }

    public function testEmptyCreatesEmptyCollection(): void
    {
        $collection = FolderDtoCollection::empty();

        self::assertTrue($collection->isEmpty());
        self::assertCount(0, $collection);
        self::assertSame([], $collection->toArray());
    }

    public function testIsEmptyReturnsTrueForEmptyCollection(): void
    {
        $collection = FolderDtoCollection::fromArray([]);

        self::assertTrue($collection->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyCollection(): void
    {
        $folder = new FolderDto('id1', 'Folder', '2025-01-01T00:00:00+00:00', null);
        $collection = FolderDtoCollection::fromArray([$folder]);

        self::assertFalse($collection->isEmpty());
    }

    public function testToArrayReturnsUnderlyingArray(): void
    {
        $folders = [
            new FolderDto('id1', 'Folder 1', '2025-01-01T00:00:00+00:00', null),
        ];
        $collection = FolderDtoCollection::fromArray($folders);

        self::assertSame($folders, $collection->toArray());
    }

    public function testCountReturnsNumberOfFolders(): void
    {
        $folders = [
            new FolderDto('id1', 'Folder 1', '2025-01-01T00:00:00+00:00', null),
            new FolderDto('id2', 'Folder 2', '2025-01-02T00:00:00+00:00', 'parent1'),
            new FolderDto('id3', 'Folder 3', '2025-01-03T00:00:00+00:00', 'parent1'),
        ];
        $collection = FolderDtoCollection::fromArray($folders);

        self::assertCount(3, $collection);
        self::assertSame(3, $collection->count());
    }

    public function testGetIteratorYieldsAllFolders(): void
    {
        $folders = [
            new FolderDto('id1', 'Folder 1', '2025-01-01T00:00:00+00:00', null),
            new FolderDto('id2', 'Folder 2', '2025-01-02T00:00:00+00:00', 'parent1'),
        ];
        $collection = FolderDtoCollection::fromArray($folders);

        $iterated = [];
        foreach ($collection as $key => $folder) {
            $iterated[$key] = $folder;
        }

        self::assertSame($folders, $iterated);
    }

    public function testCollectionIsTraversable(): void
    {
        $folders = [
            new FolderDto('id1', 'Folder', '2025-01-01T00:00:00+00:00', null),
        ];
        $collection = FolderDtoCollection::fromArray($folders);

        $result = iterator_to_array($collection);

        self::assertSame($folders, $result);
    }
}
