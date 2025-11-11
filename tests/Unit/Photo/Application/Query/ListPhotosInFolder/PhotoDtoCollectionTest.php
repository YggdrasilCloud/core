<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Query\ListPhotosInFolder;

use App\Photo\Application\Query\ListPhotosInFolder\PhotoDto;
use App\Photo\Application\Query\ListPhotosInFolder\PhotoDtoCollection;
use PHPUnit\Framework\TestCase;

final class PhotoDtoCollectionTest extends TestCase
{
    public function testFromArrayCreatesCollection(): void
    {
        $photos = [
            new PhotoDto('id1', 'photo1.jpg', 'storage/key1', 'image/jpeg', 1024, '2025-01-01T00:00:00+00:00', null, '/api/photos/id1/file', '/api/photos/id1/thumbnail'),
            new PhotoDto('id2', 'photo2.jpg', 'storage/key2', 'image/png', 2048, '2025-01-02T00:00:00+00:00', null, '/api/photos/id2/file', '/api/photos/id2/thumbnail'),
        ];

        $collection = PhotoDtoCollection::fromArray($photos);

        self::assertSame($photos, $collection->toArray());
        self::assertCount(2, $collection);
    }

    public function testEmptyCreatesEmptyCollection(): void
    {
        $collection = PhotoDtoCollection::empty();

        self::assertTrue($collection->isEmpty());
        self::assertCount(0, $collection);
        self::assertSame([], $collection->toArray());
    }

    public function testIsEmptyReturnsTrueForEmptyCollection(): void
    {
        $collection = PhotoDtoCollection::fromArray([]);

        self::assertTrue($collection->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyCollection(): void
    {
        $photo = new PhotoDto('id1', 'photo.jpg', 'storage/key1', 'image/jpeg', 1024, '2025-01-01T00:00:00+00:00', null, '/api/photos/id1/file', '/api/photos/id1/thumbnail');
        $collection = PhotoDtoCollection::fromArray([$photo]);

        self::assertFalse($collection->isEmpty());
    }

    public function testToArrayReturnsUnderlyingArray(): void
    {
        $photos = [
            new PhotoDto('id1', 'photo1.jpg', 'storage/key1', 'image/jpeg', 1024, '2025-01-01T00:00:00+00:00', null, '/api/photos/id1/file', '/api/photos/id1/thumbnail'),
        ];
        $collection = PhotoDtoCollection::fromArray($photos);

        self::assertSame($photos, $collection->toArray());
    }

    public function testCountReturnsNumberOfPhotos(): void
    {
        $photos = [
            new PhotoDto('id1', 'photo1.jpg', 'storage/key1', 'image/jpeg', 1024, '2025-01-01T00:00:00+00:00', null, '/api/photos/id1/file', '/api/photos/id1/thumbnail'),
            new PhotoDto('id2', 'photo2.jpg', 'storage/key2', 'image/png', 2048, '2025-01-02T00:00:00+00:00', null, '/api/photos/id2/file', '/api/photos/id2/thumbnail'),
            new PhotoDto('id3', 'photo3.jpg', 'storage/key3', 'image/gif', 3072, '2025-01-03T00:00:00+00:00', null, '/api/photos/id3/file', '/api/photos/id3/thumbnail'),
        ];
        $collection = PhotoDtoCollection::fromArray($photos);

        self::assertCount(3, $collection);
        self::assertSame(3, $collection->count());
    }

    public function testGetIteratorYieldsAllPhotos(): void
    {
        $photos = [
            new PhotoDto('id1', 'photo1.jpg', 'storage/key1', 'image/jpeg', 1024, '2025-01-01T00:00:00+00:00', null, '/api/photos/id1/file', '/api/photos/id1/thumbnail'),
            new PhotoDto('id2', 'photo2.jpg', 'storage/key2', 'image/png', 2048, '2025-01-02T00:00:00+00:00', null, '/api/photos/id2/file', '/api/photos/id2/thumbnail'),
        ];
        $collection = PhotoDtoCollection::fromArray($photos);

        $iterated = [];
        foreach ($collection as $key => $photo) {
            $iterated[$key] = $photo;
        }

        self::assertSame($photos, $iterated);
    }

    public function testCollectionIsTraversable(): void
    {
        $photos = [
            new PhotoDto('id1', 'photo1.jpg', 'storage/key1', 'image/jpeg', 1024, '2025-01-01T00:00:00+00:00', null, '/api/photos/id1/file', '/api/photos/id1/thumbnail'),
        ];
        $collection = PhotoDtoCollection::fromArray($photos);

        $result = iterator_to_array($collection);

        self::assertSame($photos, $result);
    }
}
