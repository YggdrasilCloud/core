<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Command\BulkDeletePhotos;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Application\Command\BulkDeletePhotos\BulkDeletePhotos;
use App\Photo\Application\Command\BulkDeletePhotos\BulkDeletePhotosHandler;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BulkDeletePhotosHandlerTest extends TestCase
{
    private MockObject&PhotoRepositoryInterface $photoRepository;
    private FileStorageInterface&MockObject $fileStorage;
    private BulkDeletePhotosHandler $handler;

    protected function setUp(): void
    {
        $this->photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $this->fileStorage = $this->createMock(FileStorageInterface::class);

        $this->handler = new BulkDeletePhotosHandler(
            $this->photoRepository,
            $this->fileStorage,
        );
    }

    public function testDeletesAllPhotosSuccessfully(): void
    {
        $photoId1 = '550e8400-e29b-41d4-a716-446655440001';
        $photoId2 = '550e8400-e29b-41d4-a716-446655440002';

        $photo1 = $this->createPhoto($photoId1);
        $photo2 = $this->createPhoto($photoId2);

        $this->photoRepository
            ->method('findById')
            ->willReturnCallback(static function (PhotoId $id) use ($photo1, $photo2, $photoId1): Photo {
                return $id->toString() === $photoId1 ? $photo1 : $photo2;
            })
        ;

        $this->fileStorage
            ->expects(self::exactly(2))
            ->method('delete')
        ;

        $this->photoRepository
            ->expects(self::exactly(2))
            ->method('remove')
        ;

        $command = new BulkDeletePhotos([$photoId1, $photoId2]);
        $result = ($this->handler)($command);

        self::assertSame(2, $result->deletedCount());
        self::assertSame(0, $result->failedCount());
        self::assertContains($photoId1, $result->deleted);
        self::assertContains($photoId2, $result->deleted);
    }

    public function testReportsNotFoundPhotosAsFailures(): void
    {
        $existingId = '550e8400-e29b-41d4-a716-446655440001';
        $notFoundId = '550e8400-e29b-41d4-a716-446655440002';

        $photo = $this->createPhoto($existingId);

        $this->photoRepository
            ->method('findById')
            ->willReturnCallback(static function (PhotoId $id) use ($photo, $existingId): ?Photo {
                return $id->toString() === $existingId ? $photo : null;
            })
        ;

        $this->fileStorage
            ->expects(self::once())
            ->method('delete')
        ;

        $this->photoRepository
            ->expects(self::once())
            ->method('remove')
        ;

        $command = new BulkDeletePhotos([$existingId, $notFoundId]);
        $result = ($this->handler)($command);

        self::assertSame(1, $result->deletedCount());
        self::assertSame(1, $result->failedCount());
        self::assertContains($existingId, $result->deleted);
        self::assertSame('Photo not found', $result->failed[0]->reason);
    }

    public function testCatchesStorageExceptionsAndReportsAsFailures(): void
    {
        $photoId = '550e8400-e29b-41d4-a716-446655440001';
        $photo = $this->createPhoto($photoId);

        $this->photoRepository
            ->method('findById')
            ->willReturn($photo)
        ;

        $this->fileStorage
            ->method('delete')
            ->willThrowException(new RuntimeException('Storage error'))
        ;

        $command = new BulkDeletePhotos([$photoId]);
        $result = ($this->handler)($command);

        self::assertSame(0, $result->deletedCount());
        self::assertSame(1, $result->failedCount());
        self::assertSame('Delete operation failed', $result->failed[0]->reason);
    }

    public function testDeletesThumbnailWhenPresent(): void
    {
        $photoId = '550e8400-e29b-41d4-a716-446655440001';
        $photo = $this->createPhotoWithThumbnail($photoId, 'thumbs/photo.jpg');

        $this->photoRepository
            ->method('findById')
            ->willReturn($photo)
        ;

        // Should delete both main file and thumbnail
        $this->fileStorage
            ->expects(self::exactly(2))
            ->method('delete')
        ;

        $this->photoRepository
            ->expects(self::once())
            ->method('remove')
        ;

        $command = new BulkDeletePhotos([$photoId]);
        $result = ($this->handler)($command);

        self::assertSame(1, $result->deletedCount());
    }

    private function createPhoto(string $id): Photo
    {
        return Photo::upload(
            PhotoId::fromString($id),
            FolderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440099'),
            FileName::fromString('photo.jpg'),
            'photos/test/photo.jpg',
            'local',
            'image/jpeg',
            1024
        );
    }

    private function createPhotoWithThumbnail(string $id, string $thumbnailKey): Photo
    {
        return Photo::upload(
            PhotoId::fromString($id),
            FolderId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440099'),
            FileName::fromString('photo.jpg'),
            'photos/test/photo.jpg',
            'local',
            'image/jpeg',
            1024,
            $thumbnailKey
        );
    }
}
