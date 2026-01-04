<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Service;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Criteria\PhotoCriteria;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Photo\Domain\Service\FolderContentDeleter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FolderContentDeleterTest extends TestCase
{
    private MockObject&PhotoRepositoryInterface $photoRepository;
    private FolderRepositoryInterface&MockObject $folderRepository;
    private FileStorageInterface&MockObject $fileStorage;
    private MockObject $folderStorage;
    private MockObject $pathBuilder;
    private FolderContentDeleter $deleter;

    protected function setUp(): void
    {
        $this->photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $this->folderRepository = $this->createMock(FolderRepositoryInterface::class);
        $this->fileStorage = $this->createMock(FileStorageInterface::class);
        // @phpstan-ignore-next-line BypassFinals allows mocking final classes
        $this->folderStorage = $this->createMock(PhysicalFolderStorage::class);
        // @phpstan-ignore-next-line BypassFinals allows mocking final classes
        $this->pathBuilder = $this->createMock(FileSystemPathBuilder::class);

        $this->deleter = new FolderContentDeleter(
            $this->photoRepository,
            $this->folderRepository,
            $this->fileStorage,
            $this->folderStorage,
            $this->pathBuilder,
        );
    }

    public function testDeleteAllContentReturnsEmptyWhenFolderIsEmpty(): void
    {
        $folderId = FolderId::generate();

        $this->photoRepository->method('findByFolderId')
            ->willReturn([])
        ;

        $this->folderRepository->method('findByParentId')
            ->willReturn([])
        ;

        $result = $this->deleter->deleteAllContent($folderId);

        self::assertSame(0, $result->photosDeleted);
        self::assertSame(0, $result->subfoldersDeleted);
    }

    public function testDeleteAllContentDeletesPhotos(): void
    {
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $photo1 = $this->createPhoto($folderId, $ownerId, 'photo1.jpg', 'photos/photo1.jpg');
        $photo2 = $this->createPhoto($folderId, $ownerId, 'photo2.jpg', 'photos/photo2.jpg');

        $this->photoRepository->expects(self::once())
            ->method('findByFolderId')
            ->with($folderId, self::isInstanceOf(PhotoCriteria::class), 100, 0)
            ->willReturn([$photo1, $photo2])
        ;

        $this->folderRepository->expects(self::once())
            ->method('findByParentId')
            ->willReturn([])
        ;

        $this->fileStorage->expects(self::exactly(2))
            ->method('exists')
            ->willReturn(true)
        ;

        $this->fileStorage->expects(self::exactly(2))
            ->method('delete')
        ;

        $this->photoRepository->expects(self::exactly(2))
            ->method('remove')
        ;

        $result = $this->deleter->deleteAllContent($folderId);

        self::assertSame(2, $result->photosDeleted);
        self::assertSame(0, $result->subfoldersDeleted);
    }

    public function testDeleteAllContentDeletesPhotosWithThumbnails(): void
    {
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $photo = $this->createPhoto($folderId, $ownerId, 'photo.jpg', 'photos/photo.jpg', 'thumbs/photo.jpg');

        $this->photoRepository->method('findByFolderId')
            ->willReturn([$photo])
        ;

        $this->folderRepository->method('findByParentId')
            ->willReturn([])
        ;

        // Both original and thumbnail exist
        $this->fileStorage->method('exists')
            ->willReturn(true)
        ;

        // Should delete both original and thumbnail
        $this->fileStorage->expects(self::exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (string $key): void {
                self::assertContains($key, ['photos/photo.jpg', 'thumbs/photo.jpg']);
            })
        ;

        $this->photoRepository->expects(self::once())
            ->method('remove')
            ->with($photo)
        ;

        $result = $this->deleter->deleteAllContent($folderId);

        self::assertSame(1, $result->photosDeleted);
    }

    public function testDeleteAllContentDeletesSubfoldersRecursively(): void
    {
        $parentId = FolderId::generate();
        $childId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $childFolder = Folder::create(
            $childId,
            FolderName::fromString('Child'),
            $ownerId,
            $parentId
        );

        $childPhoto = $this->createPhoto($childId, $ownerId, 'child-photo.jpg', 'photos/child-photo.jpg');

        // Parent folder: no photos, has one subfolder
        $this->photoRepository->method('findByFolderId')
            ->willReturnCallback(static fn (FolderId $id) => $id->equals($childId) ? [$childPhoto] : [])
        ;

        $this->folderRepository->method('findByParentId')
            ->willReturnCallback(static fn (FolderId $id) => $id->equals($parentId) ? [$childFolder] : [])
        ;

        $this->fileStorage->method('exists')
            ->willReturn(true)
        ;

        $this->fileStorage->expects(self::once())
            ->method('delete')
            ->with('photos/child-photo.jpg')
        ;

        $this->photoRepository->expects(self::once())
            ->method('remove')
            ->with($childPhoto)
        ;

        // Path builder should build path for child folder
        $this->pathBuilder->expects(self::once())
            ->method('buildFolderPath')
            ->with($childFolder)
            ->willReturn('photos/Child')
        ;

        // Folder storage should remove the directory
        $this->folderStorage->expects(self::once())
            ->method('removeEmptyDirectory')
            ->with('photos/Child')
        ;

        $this->folderRepository->expects(self::once())
            ->method('remove')
            ->with($childFolder)
        ;

        $result = $this->deleter->deleteAllContent($parentId);

        self::assertSame(1, $result->photosDeleted);
        self::assertSame(1, $result->subfoldersDeleted);
    }

    public function testDeleteAllContentHandlesDeepNesting(): void
    {
        $level1 = FolderId::generate();
        $level2 = FolderId::generate();
        $level3 = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $folder2 = Folder::create($level2, FolderName::fromString('Level2'), $ownerId, $level1);
        $folder3 = Folder::create($level3, FolderName::fromString('Level3'), $ownerId, $level2);

        $photo = $this->createPhoto($level3, $ownerId, 'deep-photo.jpg', 'photos/deep-photo.jpg');

        $this->photoRepository->method('findByFolderId')
            ->willReturnCallback(static fn (FolderId $id) => $id->equals($level3) ? [$photo] : [])
        ;

        $this->folderRepository->method('findByParentId')
            ->willReturnCallback(static function (FolderId $id) use ($level1, $level2, $folder2, $folder3): array {
                if ($id->equals($level1)) {
                    return [$folder2];
                }
                if ($id->equals($level2)) {
                    return [$folder3];
                }

                return [];
            })
        ;

        $this->fileStorage->method('exists')->willReturn(true);

        // Path builder should be called for both subfolders
        $this->pathBuilder->method('buildFolderPath')
            ->willReturnCallback(static function (Folder $folder) use ($folder2, $folder3): string {
                if ($folder === $folder3) {
                    return 'photos/Level2/Level3';
                }
                if ($folder === $folder2) {
                    return 'photos/Level2';
                }

                return 'photos/unknown';
            })
        ;

        // Folder storage should remove both directories (depth-first: Level3 then Level2)
        $this->folderStorage->expects(self::exactly(2))
            ->method('removeEmptyDirectory')
        ;

        $result = $this->deleter->deleteAllContent($level1);

        self::assertSame(1, $result->photosDeleted);
        self::assertSame(2, $result->subfoldersDeleted);
    }

    public function testDeleteAllContentSkipsNonExistentFiles(): void
    {
        $folderId = FolderId::generate();
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $photo = $this->createPhoto($folderId, $ownerId, 'missing.jpg', 'photos/missing.jpg');

        $this->photoRepository->method('findByFolderId')
            ->willReturn([$photo])
        ;

        $this->folderRepository->method('findByParentId')
            ->willReturn([])
        ;

        // File doesn't exist in storage
        $this->fileStorage->method('exists')
            ->willReturn(false)
        ;

        // Should not call delete
        $this->fileStorage->expects(self::never())
            ->method('delete')
        ;

        // Should still remove from database
        $this->photoRepository->expects(self::once())
            ->method('remove')
            ->with($photo)
        ;

        $result = $this->deleter->deleteAllContent($folderId);

        self::assertSame(1, $result->photosDeleted);
    }

    private function createPhoto(
        FolderId $folderId,
        UserId $ownerId,
        string $fileName,
        string $storageKey,
        ?string $thumbnailKey = null
    ): Photo {
        return Photo::upload(
            PhotoId::generate(),
            $folderId,
            $ownerId,
            FileName::fromString($fileName),
            $storageKey,
            'local',
            'image/jpeg',
            1024,
            $thumbnailKey,
        );
    }
}
