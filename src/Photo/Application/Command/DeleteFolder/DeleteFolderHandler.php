<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\DeleteFolder;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Criteria\FolderCriteria;
use App\Photo\Domain\Criteria\PhotoCriteria;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use DomainException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function sprintf;

/**
 * Handles folder deletion.
 *
 * Deletes both the folder entity from the database and the physical directory
 * from the filesystem.
 *
 * BUSINESS RULE: A folder can only be deleted if it's empty (no photos, no subfolders).
 * This prevents accidental data loss and ensures users explicitly delete content first.
 */
#[AsMessageHandler]
final readonly class DeleteFolderHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private PhotoRepositoryInterface $photoRepository,
        private PhysicalFolderStorage $folderStorage,
        private FileSystemPathBuilder $pathBuilder,
    ) {}

    public function __invoke(DeleteFolderCommand $command): void
    {
        $folderId = FolderId::fromString($command->folderId);

        // Verify folder exists
        $folder = $this->folderRepository->findById($folderId);
        if ($folder === null) {
            throw new DomainException(sprintf('Folder not found: %s', $command->folderId));
        }

        // Ensure folder is empty - no photos
        $photoCount = $this->photoRepository->countByFolderId($folderId, new PhotoCriteria());
        if ($photoCount > 0) {
            throw new DomainException(sprintf(
                'Cannot delete folder: contains %d photo(s). Delete all photos first.',
                $photoCount,
            ));
        }

        // Ensure folder is empty - no subfolders
        $subfolderCount = $this->folderRepository->countByParentId($folderId, new FolderCriteria());
        if ($subfolderCount > 0) {
            throw new DomainException(sprintf(
                'Cannot delete folder: contains %d subfolder(s). Delete all subfolders first.',
                $subfolderCount,
            ));
        }

        // Get folder path before deletion
        $folderPath = $this->pathBuilder->buildFolderPath($folder);

        // Remove folder from database
        $this->folderRepository->remove($folder);

        // Remove physical directory from filesystem
        // This will only succeed if the directory is truly empty
        $this->folderStorage->removeEmptyDirectory($folderPath);
    }
}
