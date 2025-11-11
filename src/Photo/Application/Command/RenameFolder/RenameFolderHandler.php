<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\RenameFolder;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use DomainException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function sprintf;

/**
 * Handles folder renaming.
 *
 * Renames both the folder entity and the physical directory on the filesystem.
 *
 * IMPORTANT NOTE: This implementation does NOT update storage keys of photos
 * within the renamed folder. This means that after renaming:
 * - The physical files will be in the new path (moved by directory rename)
 * - But storageKeys in the database will still reference the old path
 *
 * This is acceptable for now because:
 * 1. Photo access uses the Photo entity's fileUrl/thumbnailUrl which are generated
 *    dynamically from the folder hierarchy, not from the storageKey
 * 2. The storageKey is mainly used for direct file storage operations
 *
 * Future improvement: Implement a background job to update all affected photo
 * storageKeys after a folder rename. This would require:
 * - Finding all photos in the renamed folder (and subfolders)
 * - Recalculating their storage paths
 * - Updating their storageKeys in the database
 */
#[AsMessageHandler]
final readonly class RenameFolderHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private PhysicalFolderStorage $folderStorage,
        private FileSystemPathBuilder $pathBuilder,
    ) {}

    public function __invoke(RenameFolderCommand $command): void
    {
        $folderId = FolderId::fromString($command->folderId);

        // Verify folder exists
        $folder = $this->folderRepository->findById($folderId);
        if ($folder === null) {
            throw new DomainException(sprintf('Folder not found: %s', $command->folderId));
        }

        // Get old path before renaming
        $oldPath = $this->pathBuilder->buildFolderPath($folder);

        // Rename folder entity
        $newFolderName = FolderName::fromString($command->newFolderName);
        $folder->rename($newFolderName);

        // Save renamed folder to database
        $this->folderRepository->save($folder);

        // Get new path after renaming
        $newPath = $this->pathBuilder->buildFolderPath($folder);

        // Rename physical directory on filesystem
        // This automatically moves all files and subdirectories
        $this->folderStorage->renameDirectory($oldPath, $newPath);
    }
}
