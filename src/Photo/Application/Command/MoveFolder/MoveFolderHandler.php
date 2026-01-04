<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\MoveFolder;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Photo\Domain\Service\FolderHierarchyValidator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles folder moving.
 *
 * Moves a folder to a new parent (or to root if targetParentId is null).
 * Validates hierarchy rules and renames the physical directory on the filesystem.
 *
 * IMPORTANT NOTE: This implementation does NOT update storage keys of photos
 * within the moved folder. See RenameFolderHandler for the same limitation.
 */
#[AsMessageHandler]
final readonly class MoveFolderHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FolderHierarchyValidator $hierarchyValidator,
        private PhysicalFolderStorage $folderStorage,
        private FileSystemPathBuilder $pathBuilder,
    ) {}

    public function __invoke(MoveFolderCommand $command): void
    {
        $folderId = FolderId::fromString($command->folderId);

        // Verify folder exists
        $folder = $this->folderRepository->findById($folderId);
        if ($folder === null) {
            throw FolderNotFoundException::withId($folderId);
        }

        // Convert target parent ID
        $targetParentId = $command->targetParentId !== null
            ? FolderId::fromString($command->targetParentId)
            : null;

        // Check if already at target location (no-op)
        $currentParentId = $folder->parentId();
        $currentParentIdString = $currentParentId?->toString();
        if ($currentParentIdString === $command->targetParentId) {
            return;
        }

        // Validate hierarchy (no cycles, parent exists)
        $this->hierarchyValidator->validateParent($folder, $targetParentId);

        // Get old path before moving
        $oldPath = $this->pathBuilder->buildFolderPath($folder);

        // Move folder entity
        $folder->move($targetParentId);

        // Save moved folder to database
        $this->folderRepository->save($folder);

        // Get new path after moving
        $newPath = $this->pathBuilder->buildFolderPath($folder);

        // Rename physical directory on filesystem
        $this->folderStorage->renameDirectory($oldPath, $newPath);
    }
}
