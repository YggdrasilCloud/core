<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Service;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FolderHierarchyValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class FolderHierarchyValidatorTest extends TestCase
{
    public function testValidateParentAcceptsNullParent(): void
    {
        $repository = $this->createMock(FolderRepositoryInterface::class);
        $validator = new FolderHierarchyValidator($repository);

        $folder = $this->createFolder(FolderId::generate(), null);

        $validator->validateParent($folder, null);

        $this->expectNotToPerformAssertions();
    }

    public function testValidateParentRejectsSelfAsParent(): void
    {
        $repository = $this->createMock(FolderRepositoryInterface::class);
        $validator = new FolderHierarchyValidator($repository);

        $folderId = FolderId::generate();
        $folder = $this->createFolder($folderId, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A folder cannot be its own parent');

        $validator->validateParent($folder, $folderId);
    }

    public function testValidateParentRejectsNonExistentParent(): void
    {
        $repository = $this->createMock(FolderRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $validator = new FolderHierarchyValidator($repository);

        $folder = $this->createFolder(FolderId::generate(), null);
        $nonExistentParentId = FolderId::generate();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent folder not found');

        $validator->validateParent($folder, $nonExistentParentId);
    }

    public function testValidateParentAcceptsValidParent(): void
    {
        $parentId = FolderId::generate();
        $parent = $this->createFolder($parentId, null);

        $repository = $this->createMock(FolderRepositoryInterface::class);
        $repository->method('findById')
            ->with($parentId)
            ->willReturn($parent)
        ;

        $validator = new FolderHierarchyValidator($repository);

        $folder = $this->createFolder(FolderId::generate(), null);

        $validator->validateParent($folder, $parentId);

        // No exception thrown = success
        $this->addToAssertionCount(1);
    }

    public function testValidateParentRejectsDescendantAsParent(): void
    {
        // Create hierarchy: root -> child -> grandchild
        $rootId = FolderId::generate();
        $childId = FolderId::generate();
        $grandchildId = FolderId::generate();

        $root = $this->createFolder($rootId, null);
        $child = $this->createFolder($childId, $rootId);
        $grandchild = $this->createFolder($grandchildId, $childId);

        $repository = $this->createMock(FolderRepositoryInterface::class);
        $repository->method('findById')->willReturnMap([
            [$grandchildId, $grandchild],
            [$childId, $child],
            [$rootId, $root],
        ]);

        $validator = new FolderHierarchyValidator($repository);

        // Try to set grandchild as parent of root (would create a cycle)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set parent: would create a cycle');

        $validator->validateParent($root, $grandchildId);
    }

    public function testValidateParentDetectsExistingCycle(): void
    {
        // Create a cycle: folder1 -> folder2 -> folder1
        $folder1Id = FolderId::generate();
        $folder2Id = FolderId::generate();

        $folder1 = $this->createFolder($folder1Id, $folder2Id);
        $folder2 = $this->createFolder($folder2Id, $folder1Id);

        $repository = $this->createMock(FolderRepositoryInterface::class);
        $repository->method('findById')->willReturnMap([
            [$folder1Id, $folder1],
            [$folder2Id, $folder2],
        ]);

        $validator = new FolderHierarchyValidator($repository);

        $folder3 = $this->createFolder(FolderId::generate(), null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cycle detected in existing folder hierarchy');

        $validator->validateParent($folder3, $folder1Id);
    }

    private function createFolder(FolderId $id, ?FolderId $parentId): Folder
    {
        return Folder::create(
            $id,
            FolderName::fromString('Test Folder'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $parentId,
        );
    }
}
