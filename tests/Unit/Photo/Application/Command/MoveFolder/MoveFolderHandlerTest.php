<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Application\Command\MoveFolder;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Application\Command\MoveFolder\MoveFolderCommand;
use App\Photo\Application\Command\MoveFolder\MoveFolderHandler;
use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Photo\Domain\Service\FolderHierarchyValidator;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MoveFolderHandlerTest extends TestCase
{
    private MockObject $folderRepository;
    private MockObject $hierarchyValidator;
    private MockObject $folderStorage;
    private MockObject $pathBuilder;
    private MoveFolderHandler $handler;

    protected function setUp(): void
    {
        $this->folderRepository = $this->createMock(FolderRepositoryInterface::class);
        // @phpstan-ignore-next-line BypassFinals allows mocking final classes
        $this->hierarchyValidator = $this->createMock(FolderHierarchyValidator::class);
        // @phpstan-ignore-next-line BypassFinals allows mocking final classes
        $this->folderStorage = $this->createMock(PhysicalFolderStorage::class);
        // @phpstan-ignore-next-line BypassFinals allows mocking final classes
        $this->pathBuilder = $this->createMock(FileSystemPathBuilder::class);

        $this->handler = new MoveFolderHandler(
            $this->folderRepository,
            $this->hierarchyValidator,
            $this->folderStorage,
            $this->pathBuilder,
        );
    }

    public function testMovesFolderToNewParentSuccessfully(): void
    {
        $folderId = '550e8400-e29b-41d4-a716-446655440001';
        $newParentId = '550e8400-e29b-41d4-a716-446655440002';

        $folder = $this->createFolder($folderId, null);

        $this->folderRepository
            ->method('findById')
            ->willReturn($folder)
        ;

        $this->hierarchyValidator
            ->expects(self::once())
            ->method('validateParent')
        ;

        $this->pathBuilder
            ->method('buildFolderPath')
            ->willReturnOnConsecutiveCalls('photos/OldPath', 'photos/NewParent/OldPath')
        ;

        $this->folderRepository
            ->expects(self::once())
            ->method('save')
        ;

        $this->folderStorage
            ->expects(self::once())
            ->method('renameDirectory')
            ->with('photos/OldPath', 'photos/NewParent/OldPath')
        ;

        $command = new MoveFolderCommand($folderId, $newParentId);
        ($this->handler)($command);

        self::assertSame($newParentId, $folder->parentId()?->toString());
    }

    public function testMovesFolderToRootSuccessfully(): void
    {
        $folderId = '550e8400-e29b-41d4-a716-446655440001';
        $currentParentId = '550e8400-e29b-41d4-a716-446655440002';

        $folder = $this->createFolder($folderId, $currentParentId);

        $this->folderRepository
            ->method('findById')
            ->willReturn($folder)
        ;

        $this->hierarchyValidator
            ->expects(self::once())
            ->method('validateParent')
        ;

        $this->pathBuilder
            ->method('buildFolderPath')
            ->willReturnOnConsecutiveCalls('photos/Parent/Folder', 'photos/Folder')
        ;

        $this->folderRepository
            ->expects(self::once())
            ->method('save')
        ;

        $this->folderStorage
            ->expects(self::once())
            ->method('renameDirectory')
            ->with('photos/Parent/Folder', 'photos/Folder')
        ;

        $command = new MoveFolderCommand($folderId, null);
        ($this->handler)($command);

        self::assertNull($folder->parentId());
    }

    public function testNoOpWhenAlreadyAtTargetLocation(): void
    {
        $folderId = '550e8400-e29b-41d4-a716-446655440001';
        $parentId = '550e8400-e29b-41d4-a716-446655440002';

        $folder = $this->createFolder($folderId, $parentId);

        $this->folderRepository
            ->method('findById')
            ->willReturn($folder)
        ;

        $this->hierarchyValidator
            ->expects(self::never())
            ->method('validateParent')
        ;

        $this->folderRepository
            ->expects(self::never())
            ->method('save')
        ;

        $this->folderStorage
            ->expects(self::never())
            ->method('renameDirectory')
        ;

        $command = new MoveFolderCommand($folderId, $parentId);
        ($this->handler)($command);
    }

    public function testNoOpWhenAlreadyAtRootAndMovingToRoot(): void
    {
        $folderId = '550e8400-e29b-41d4-a716-446655440001';

        $folder = $this->createFolder($folderId, null);

        $this->folderRepository
            ->method('findById')
            ->willReturn($folder)
        ;

        $this->hierarchyValidator
            ->expects(self::never())
            ->method('validateParent')
        ;

        $this->folderRepository
            ->expects(self::never())
            ->method('save')
        ;

        $this->folderStorage
            ->expects(self::never())
            ->method('renameDirectory')
        ;

        $command = new MoveFolderCommand($folderId, null);
        ($this->handler)($command);
    }

    public function testThrowsWhenFolderNotFound(): void
    {
        $folderId = '550e8400-e29b-41d4-a716-446655440001';

        $this->folderRepository
            ->method('findById')
            ->willReturn(null)
        ;

        $this->expectException(FolderNotFoundException::class);

        $command = new MoveFolderCommand($folderId, null);
        ($this->handler)($command);
    }

    public function testThrowsWhenMoveWouldCreateCycle(): void
    {
        $folderId = '550e8400-e29b-41d4-a716-446655440001';
        $descendantId = '550e8400-e29b-41d4-a716-446655440002';

        $folder = $this->createFolder($folderId, null);

        $this->folderRepository
            ->method('findById')
            ->willReturn($folder)
        ;

        $this->hierarchyValidator
            ->method('validateParent')
            ->willThrowException(new InvalidArgumentException('Cannot set parent: would create a cycle in folder hierarchy'))
        ;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cycle');

        $command = new MoveFolderCommand($folderId, $descendantId);
        ($this->handler)($command);
    }

    private function createFolder(string $id, ?string $parentId): Folder
    {
        return Folder::create(
            FolderId::fromString($id),
            FolderName::fromString('Test Folder'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440099'),
            $parentId !== null ? FolderId::fromString($parentId) : null
        );
    }
}
