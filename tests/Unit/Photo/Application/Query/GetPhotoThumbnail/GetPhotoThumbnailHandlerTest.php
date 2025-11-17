<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Query\GetPhotoThumbnail;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Application\Query\GetPhotoFile\FileNotFoundException;
use App\Photo\Application\Query\GetPhotoFile\PhotoNotFoundException;
use App\Photo\Application\Query\GetPhotoThumbnail\GetPhotoThumbnailHandler;
use App\Photo\Application\Query\GetPhotoThumbnail\GetPhotoThumbnailQuery;
use App\Photo\Application\Query\GetPhotoThumbnail\ThumbnailNotFoundException;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetPhotoThumbnailHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsFileResponseModelWhenThumbnailExists(): void
    {
        $photoId = PhotoId::generate();
        $photo = Photo::upload(
            $photoId,
            FolderId::generate(),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('test.jpg'),
            'photos/test.jpg',
            'local',
            'image/jpeg',
            1024,
            'thumbs/photos/test_thumb.jpg'
        );

        $photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $photoRepository->method('findById')->willReturn($photo);

        $fileStorage = $this->createMock(FileStorageInterface::class);
        $fileStorage->method('exists')->willReturn(true);

        $handler = new GetPhotoThumbnailHandler($photoRepository, $fileStorage);
        $query = new GetPhotoThumbnailQuery($photoId->toString());

        $result = $handler($query);

        self::assertSame('thumbs/photos/test_thumb.jpg', $result->storageKey);
        self::assertSame('image/jpeg', $result->mimeType);
        self::assertSame(86400, $result->cacheMaxAge); // 24 hours
    }

    #[Test]
    public function itThrowsPhotoNotFoundWhenPhotoDoesNotExist(): void
    {
        $photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $photoRepository->method('findById')->willReturn(null);

        $fileStorage = $this->createMock(FileStorageInterface::class);

        $handler = new GetPhotoThumbnailHandler($photoRepository, $fileStorage);
        $query = new GetPhotoThumbnailQuery('550e8400-e29b-41d4-a716-446655440000');

        $this->expectException(PhotoNotFoundException::class);
        $handler($query);
    }

    #[Test]
    public function itThrowsThumbnailNotFoundWhenPhotoHasNoThumbnail(): void
    {
        $photoId = PhotoId::generate();
        $photo = Photo::upload(
            $photoId,
            FolderId::generate(),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('test.jpg'),
            'photos/test.jpg',
            'local',
            'image/jpeg',
            1024,
            null // No thumbnail
        );

        $photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $photoRepository->method('findById')->willReturn($photo);

        $fileStorage = $this->createMock(FileStorageInterface::class);

        $handler = new GetPhotoThumbnailHandler($photoRepository, $fileStorage);
        $query = new GetPhotoThumbnailQuery($photoId->toString());

        $this->expectException(ThumbnailNotFoundException::class);
        $handler($query);
    }

    #[Test]
    public function itThrowsFileNotFoundWhenThumbnailFileNotOnDisk(): void
    {
        $photoId = PhotoId::generate();
        $photo = Photo::upload(
            $photoId,
            FolderId::generate(),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('test.jpg'),
            'photos/test.jpg',
            'local',
            'image/jpeg',
            1024,
            'thumbs/photos/test_thumb.jpg'
        );

        $photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $photoRepository->method('findById')->willReturn($photo);

        $fileStorage = $this->createMock(FileStorageInterface::class);
        $fileStorage->method('exists')->willReturn(false);

        $handler = new GetPhotoThumbnailHandler($photoRepository, $fileStorage);
        $query = new GetPhotoThumbnailQuery($photoId->toString());

        $this->expectException(FileNotFoundException::class);
        $handler($query);
    }
}
