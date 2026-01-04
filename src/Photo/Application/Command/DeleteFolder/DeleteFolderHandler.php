<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\DeleteFolder;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Criteria\FolderCriteria;
use App\Photo\Domain\Criteria\PhotoCriteria;
use App\Photo\Domain\Exception\FolderNotEmptyException;
use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Photo\Domain\Model\DeletedContent;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Photo\Domain\Service\FolderContentDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles folder deletion.
 *
 * Deletes both the folder entity from the database and the physical directory
 * from the filesystem.
 *
 * When recursive is false (default), the folder must be empty.
 * When recursive is true, all photos and subfolders are deleted first.
 */
#[AsMessageHandler]
final readonly class DeleteFolderHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private PhotoRepositoryInterface $photoRepository,
        private PhysicalFolderStorage $folderStorage,
        private FileSystemPathBuilder $pathBuilder,
        private FolderContentDeleter $contentDeleter,
    ) {}

    public function __invoke(DeleteFolderCommand $command): DeletedContent
    {
        $folderId = FolderId::fromString($command->folderId);

        // Verify folder exists
        $folder = $this->folderRepository->findById($folderId);
        if ($folder === null) {
            throw FolderNotFoundException::withId($folderId);
        }

        // Count content
        $photoCount = $this->photoRepository->countByFolderId($folderId, new PhotoCriteria());
        $subfolderCount = $this->folderRepository->countByParentId($folderId, new FolderCriteria());
        $hasContent = $photoCount > 0 || $subfolderCount > 0;

        // If not recursive and has content, throw error
        if (!$command->recursive && $hasContent) {
            throw FolderNotEmptyException::withContent($folderId, $photoCount, $subfolderCount);
        }

        // Delete content recursively if needed
        $deletedContent = DeletedContent::empty();
        if ($command->recursive && $hasContent) {
            $deletedContent = $this->contentDeleter->deleteAllContent($folderId);
        }

        // Get folder path before deletion
        $folderPath = $this->pathBuilder->buildFolderPath($folder);

        // Remove folder from database
        $this->folderRepository->remove($folder);

        // Remove physical directory from filesystem
        // After content deletion, the directory should be empty
        $this->folderStorage->removeEmptyDirectory($folderPath);

        return $deletedContent;
    }
}
