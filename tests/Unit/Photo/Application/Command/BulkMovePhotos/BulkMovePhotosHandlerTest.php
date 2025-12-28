<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Command\BulkMovePhotos;

use App\Photo\Application\Command\BulkMovePhotos\BulkMovePhotos;
use App\Photo\Application\Command\BulkMovePhotos\BulkMovePhotosHandler;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BulkMovePhotosHandlerTest extends TestCase
{
    private MockObject&PhotoRepositoryInterface $photoRepository;
    private FolderRepositoryInterface&MockObject $folderRepository;
    private BulkMovePhotosHandler $handler;

    protected function setUp(): void
    {
        $this->photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $this->folderRepository = $this->createMock(FolderRepositoryInterface::class);

        $this->handler = new BulkMovePhotosHandler(
            $this->photoRepository,
            $this->folderRepository,
        );
    }

    public function testMovesAllPhotosSuccessfully(): void
    {
        $photoId1 = '550e8400-e29b-41d4-a716-446655440001';
        $photoId2 = '550e8400-e29b-41d4-a716-446655440002';
        $sourceFolderId = '550e8400-e29b-41d4-a716-446655440010';
        $targetFolderId = '550e8400-e29b-41d4-a716-446655440020';

        $targetFolder = $this->createFolder($targetFolderId);
        $photo1 = $this->createPhoto($photoId1, $sourceFolderId);
        $photo2 = $this->createPhoto($photoId2, $sourceFolderId);

        $this->folderRepository
            ->method('findById')
            ->willReturn($targetFolder)
        ;

        $this->photoRepository
            ->method('findById')
            ->willReturnCallback(static function (PhotoId $id) use ($photo1, $photo2, $photoId1): Photo {
                return $id->toString() === $photoId1 ? $photo1 : $photo2;
            })
        ;

        $this->photoRepository
            ->expects(self::exactly(2))
            ->method('save')
        ;

        $command = new BulkMovePhotos([$photoId1, $photoId2], $targetFolderId);
        $result = ($this->handler)($command);

        self::assertSame(2, $result->movedCount());
        self::assertSame(0, $result->failedCount());
        self::assertContains($photoId1, $result->moved);
        self::assertContains($photoId2, $result->moved);
    }

    public function testFailsAllWhenTargetFolderNotFound(): void
    {
        $photoId1 = '550e8400-e29b-41d4-a716-446655440001';
        $photoId2 = '550e8400-e29b-41d4-a716-446655440002';
        $targetFolderId = '550e8400-e29b-41d4-a716-446655440020';

        $this->folderRepository
            ->method('findById')
            ->willReturn(null)
        ;

        $command = new BulkMovePhotos([$photoId1, $photoId2], $targetFolderId);
        $result = ($this->handler)($command);

        self::assertSame(0, $result->movedCount());
        self::assertSame(2, $result->failedCount());
        self::assertTrue($result->allFailed());
        self::assertSame('Target folder not found', $result->failed[0]->reason);
    }

    public function testReportsNotFoundPhotosAsFailures(): void
    {
        $existingId = '550e8400-e29b-41d4-a716-446655440001';
        $notFoundId = '550e8400-e29b-41d4-a716-446655440002';
        $sourceFolderId = '550e8400-e29b-41d4-a716-446655440010';
        $targetFolderId = '550e8400-e29b-41d4-a716-446655440020';

        $targetFolder = $this->createFolder($targetFolderId);
        $photo = $this->createPhoto($existingId, $sourceFolderId);

        $this->folderRepository
            ->method('findById')
            ->willReturn($targetFolder)
        ;

        $this->photoRepository
            ->method('findById')
            ->willReturnCallback(static function (PhotoId $id) use ($photo, $existingId): ?Photo {
                return $id->toString() === $existingId ? $photo : null;
            })
        ;

        $this->photoRepository
            ->expects(self::once())
            ->method('save')
        ;

        $command = new BulkMovePhotos([$existingId, $notFoundId], $targetFolderId);
        $result = ($this->handler)($command);

        self::assertSame(1, $result->movedCount());
        self::assertSame(1, $result->failedCount());
        self::assertSame('Photo not found', $result->failed[0]->reason);
    }

    public function testReportsPhotoAlreadyInTargetFolderAsFailure(): void
    {
        $photoId = '550e8400-e29b-41d4-a716-446655440001';
        $targetFolderId = '550e8400-e29b-41d4-a716-446655440020';

        $targetFolder = $this->createFolder($targetFolderId);
        $photo = $this->createPhoto($photoId, $targetFolderId); // Already in target folder

        $this->folderRepository
            ->method('findById')
            ->willReturn($targetFolder)
        ;

        $this->photoRepository
            ->method('findById')
            ->willReturn($photo)
        ;

        $this->photoRepository
            ->expects(self::never())
            ->method('save')
        ;

        $command = new BulkMovePhotos([$photoId], $targetFolderId);
        $result = ($this->handler)($command);

        self::assertSame(0, $result->movedCount());
        self::assertSame(1, $result->failedCount());
        self::assertSame('Photo already in target folder', $result->failed[0]->reason);
    }

    private function createPhoto(string $id, string $folderId): Photo
    {
        return Photo::upload(
            PhotoId::fromString($id),
            FolderId::fromString($folderId),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440099'),
            FileName::fromString('photo.jpg'),
            'photos/test/photo.jpg',
            'local',
            'image/jpeg',
            1024
        );
    }

    private function createFolder(string $id): Folder
    {
        return Folder::create(
            FolderId::fromString($id),
            FolderName::fromString('Target Folder'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440099'),
            null
        );
    }
}
