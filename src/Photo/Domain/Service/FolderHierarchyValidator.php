<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use InvalidArgumentException;

/**
 * Domain service to validate folder hierarchy rules.
 */
final readonly class FolderHierarchyValidator
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
    ) {}

    /**
     * Validates that setting a parent won't create a cycle.
     *
     * @throws InvalidArgumentException if the parent would create a cycle
     */
    public function validateParent(Folder $folder, ?FolderId $newParentId): void
    {
        // No parent (root folder) is always valid
        if ($newParentId === null) {
            return;
        }

        // A folder cannot be its own parent
        if ($folder->id()->toString() === $newParentId->toString()) {
            throw new InvalidArgumentException('A folder cannot be its own parent');
        }

        // Check if the proposed parent exists
        $newParent = $this->folderRepository->findById($newParentId);

        if ($newParent === null) {
            throw new InvalidArgumentException("Parent folder not found: {$newParentId->toString()}");
        }

        // Check if the proposed parent is a descendant of this folder
        // (which would create a cycle)
        if ($this->isDescendant($folder->id(), $newParent)) {
            throw new InvalidArgumentException(
                'Cannot set parent: would create a cycle in folder hierarchy'
            );
        }
    }

    /**
     * Check if a folder is a descendant of the potential ancestor.
     */
    private function isDescendant(FolderId $ancestorId, Folder $potentialDescendant): bool
    {
        $current = $potentialDescendant;
        $visited = [];

        while ($current->parentId() !== null) {
            $currentId = $current->id()->toString();

            // Detect existing cycles in the hierarchy
            if (isset($visited[$currentId])) {
                throw new InvalidArgumentException("Cycle detected in existing folder hierarchy at: {$currentId}");
            }

            $visited[$currentId] = true;

            // If we reached the ancestor, it means the potential descendant is indeed a descendant
            if ($current->parentId()->toString() === $ancestorId->toString()) {
                return true;
            }

            // Move up the hierarchy
            $parent = $this->folderRepository->findById($current->parentId());

            if ($parent === null) {
                throw new InvalidArgumentException("Parent folder not found: {$current->parentId()->toString()}");
            }

            $current = $parent;
        }

        return false;
    }
}
