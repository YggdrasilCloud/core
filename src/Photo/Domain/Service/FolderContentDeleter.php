<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Criteria\FolderCriteria;
use App\Photo\Domain\Criteria\PhotoCriteria;
use App\Photo\Domain\Model\DeletedContent;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;

use function count;

/**
 * Domain service for recursive folder content deletion.
 *
 * Deletes all photos and subfolders recursively, handling:
 * - Photo files (original + thumbnails)
 * - Photo database records
 * - Subfolder physical directories
 * - Subfolder records (depth-first)
 */
final readonly class FolderContentDeleter
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private FolderRepositoryInterface $folderRepository,
        private FileStorageInterface $fileStorage,
        private PhysicalFolderStorage $folderStorage,
        private FileSystemPathBuilder $pathBuilder,
    ) {}

    /**
     * Delete all content within a folder recursively.
     *
     * Does NOT delete the folder itself - that's the caller's responsibility.
     */
    public function deleteAllContent(FolderId $folderId): DeletedContent
    {
        $result = DeletedContent::empty();

        // 1. Delete all photos in this folder
        $result = $result->merge($this->deletePhotosInFolder($folderId));

        // 2. Recursively delete all subfolders (depth-first)
        return $result->merge($this->deleteSubfolders($folderId));
    }

    private function deletePhotosInFolder(FolderId $folderId): DeletedContent
    {
        $photosDeleted = 0;
        $criteria = new PhotoCriteria();

        do {
            $photos = $this->photoRepository->findByFolderId(
                $folderId,
                $criteria,
                self::BATCH_SIZE,
                0,
            );

            foreach ($photos as $photo) {
                // Delete photo file from storage
                if ($this->fileStorage->exists($photo->storageKey())) {
                    $this->fileStorage->delete($photo->storageKey());
                }

                // Delete thumbnail if exists
                $thumbnailKey = $photo->thumbnailKey();
                if ($thumbnailKey !== null && $this->fileStorage->exists($thumbnailKey)) {
                    $this->fileStorage->delete($thumbnailKey);
                }

                // Remove from database
                $this->photoRepository->remove($photo);
                ++$photosDeleted;
            }
        } while (count($photos) === self::BATCH_SIZE);

        return new DeletedContent($photosDeleted, 0);
    }

    private function deleteSubfolders(FolderId $parentId): DeletedContent
    {
        $subfoldersDeleted = 0;
        $photosDeleted = 0;
        $criteria = new FolderCriteria();

        do {
            $subfolders = $this->folderRepository->findByParentId(
                $parentId,
                $criteria,
                self::BATCH_SIZE,
                0,
            );

            foreach ($subfolders as $subfolder) {
                // Recursively delete subfolder content first (depth-first)
                $subResult = $this->deleteAllContent($subfolder->id());
                $photosDeleted += $subResult->photosDeleted;
                $subfoldersDeleted += $subResult->subfoldersDeleted;

                // Get path before removing from DB
                $folderPath = $this->pathBuilder->buildFolderPath($subfolder);

                // Remove subfolder from database
                $this->folderRepository->remove($subfolder);

                // Remove physical directory (should be empty now)
                $this->folderStorage->removeEmptyDirectory($folderPath);

                ++$subfoldersDeleted;
            }
        } while (count($subfolders) === self::BATCH_SIZE);

        return new DeletedContent($photosDeleted, $subfoldersDeleted);
    }
}
