<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Command\UploadPhotoToFolder;

use App\File\Domain\Model\StoredObject;
use App\File\Domain\Port\FileStorageInterface;
use App\File\Domain\Service\FileCollisionResolver;
use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderCommand;
use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderHandler;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Photo\Domain\Service\ThumbnailGenerator;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UploadPhotoToFolderHandlerTest extends TestCase
{
    private MockObject&PhotoRepositoryInterface $photoRepository;
    private FolderRepositoryInterface&MockObject $folderRepository;
    private FileStorageInterface&MockObject $fileStorage;
    private ThumbnailGenerator $thumbnailGenerator;
    private FileSystemPathBuilder&MockObject $pathBuilder;
    private FileCollisionResolver&MockObject $collisionResolver;
    private UploadPhotoToFolderHandler $handler;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->photoRepository = $this->createMock(PhotoRepositoryInterface::class);
        $this->folderRepository = $this->createMock(FolderRepositoryInterface::class);
        $this->fileStorage = $this->createMock(FileStorageInterface::class);
        $this->pathBuilder = $this->createMock(FileSystemPathBuilder::class);
        $this->collisionResolver = $this->createMock(FileCollisionResolver::class);

        $this->tempDir = sys_get_temp_dir().'/upload_handler_test_'.uniqid();
        mkdir($this->tempDir, 0755, true);

        $logger = $this->createMock(LoggerInterface::class);
        $this->thumbnailGenerator = new ThumbnailGenerator($this->tempDir, $logger);

        $this->handler = new UploadPhotoToFolderHandler(
            $this->photoRepository,
            $this->folderRepository,
            $this->fileStorage,
            $this->thumbnailGenerator,
            $this->pathBuilder,
            $this->collisionResolver,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testInvokeThrowsExceptionWhenFolderNotFound(): void
    {
        $command = $this->createCommand();

        $this->folderRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Folder not found');

        ($this->handler)($command);
    }

    public function testInvokeSavesPhotoSuccessfully(): void
    {
        $command = $this->createCommand();
        $folder = $this->createFolder($command->folderId);

        // Create a real source image for thumbnail generation
        $sourceKey = 'photos/Test Folder/test-photo.jpg';
        $sourcePath = $this->tempDir.'/'.$sourceKey;
        mkdir(dirname($sourcePath), 0755, true);

        $image = imagecreatetruecolor(400, 300);
        self::assertNotFalse($image);
        imagejpeg($image, $sourcePath);
        imagedestroy($image);

        $this->folderRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($folder)
        ;

        $this->pathBuilder
            ->expects(self::once())
            ->method('buildFilePath')
            ->with($folder, $command->fileName)
            ->willReturn($sourceKey)
        ;

        $this->collisionResolver
            ->expects(self::once())
            ->method('resolveUniquePath')
            ->with($sourceKey)
            ->willReturn($sourceKey)
        ;

        $storedObject = new StoredObject(
            key: $sourceKey,
            adapter: 'local',
            storedAt: new DateTimeImmutable()
        );

        $this->fileStorage
            ->expects(self::once())
            ->method('save')
            ->with(
                self::identicalTo($command->fileStream),
                $sourceKey,
                $command->mimeType,
                $command->sizeInBytes
            )
            ->willReturn($storedObject)
        ;

        $this->photoRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Photo $photo) use ($command): bool {
                return $photo->folderId()->toString() === $command->folderId
                    && $photo->fileName()->toString() === $command->fileName
                    && $photo->mimeType() === $command->mimeType
                    && $photo->sizeInBytes() === $command->sizeInBytes
                    && $photo->thumbnailKey() !== null; // Thumbnail should be generated
            }))
        ;

        ($this->handler)($command);
    }

    public function testInvokeContinuesWhenThumbnailGenerationFails(): void
    {
        $command = $this->createCommand();
        $folder = $this->createFolder($command->folderId);

        $this->folderRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($folder)
        ;

        $sourceKey = 'photos/Test Folder/test-photo.jpg';

        $this->pathBuilder
            ->expects(self::once())
            ->method('buildFilePath')
            ->with($folder, $command->fileName)
            ->willReturn($sourceKey)
        ;

        $this->collisionResolver
            ->expects(self::once())
            ->method('resolveUniquePath')
            ->with($sourceKey)
            ->willReturn($sourceKey)
        ;

        // Don't create the source image file - this will cause thumbnail generation to fail
        $storedObject = new StoredObject(
            key: $sourceKey,
            adapter: 'local',
            storedAt: new DateTimeImmutable()
        );

        $this->fileStorage
            ->expects(self::once())
            ->method('save')
            ->willReturn($storedObject)
        ;

        $this->photoRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Photo $photo): bool {
                // Photo should be saved without thumbnail
                return $photo->thumbnailKey() === null;
            }))
        ;

        ($this->handler)($command);
    }

    public function testInvokeUsesCorrectStorageKey(): void
    {
        $command = $this->createCommand();
        $folder = $this->createFolder($command->folderId);

        $this->folderRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($folder)
        ;

        $expectedStorageKey = 'photos/Test Folder/test-photo.jpg';

        $this->pathBuilder
            ->expects(self::once())
            ->method('buildFilePath')
            ->with($folder, $command->fileName)
            ->willReturn($expectedStorageKey)
        ;

        $this->collisionResolver
            ->expects(self::once())
            ->method('resolveUniquePath')
            ->with($expectedStorageKey)
            ->willReturn($expectedStorageKey)
        ;

        $this->fileStorage
            ->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                $expectedStorageKey,
                self::anything(),
                self::anything()
            )
            ->willReturn(new StoredObject(
                key: $expectedStorageKey,
                adapter: 'local',
                storedAt: new DateTimeImmutable()
            ))
        ;

        $this->photoRepository
            ->expects(self::once())
            ->method('save')
        ;

        ($this->handler)($command);
    }

    private function createCommand(): UploadPhotoToFolderCommand
    {
        $stream = fopen('php://memory', 'rb+');
        self::assertNotFalse($stream);
        fwrite($stream, 'test image content');
        rewind($stream);

        return new UploadPhotoToFolderCommand(
            photoId: '550e8400-e29b-41d4-a716-446655440001',
            folderId: '550e8400-e29b-41d4-a716-446655440002',
            ownerId: '550e8400-e29b-41d4-a716-446655440003',
            fileName: 'test-photo.jpg',
            fileStream: $stream,
            mimeType: 'image/jpeg',
            sizeInBytes: 1024
        );
    }

    private function createFolder(string $folderId): Folder
    {
        return Folder::create(
            FolderId::fromString($folderId),
            FolderName::fromString('Test Folder'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440003'),
            null
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
