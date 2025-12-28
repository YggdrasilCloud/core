<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\BulkMovePhotos;

use App\Photo\Domain\Model\BulkOperationFailure;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Handles bulk photo movement to a target folder.
 *
 * Validates the target folder exists before processing any photos.
 * Processes each photo individually, allowing partial success.
 * Photos already in the target folder are reported as failures.
 */
#[AsMessageHandler]
final readonly class BulkMovePhotosHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(BulkMovePhotos $command): BulkMoveResult
    {
        $moved = [];
        $failed = [];

        // Validate target folder exists first
        $targetFolderId = FolderId::fromString($command->targetFolderId);
        $targetFolder = $this->folderRepository->findById($targetFolderId);

        if ($targetFolder === null) {
            // All operations fail if target doesn't exist
            foreach ($command->photoIds as $photoIdString) {
                $failed[] = new BulkOperationFailure(
                    $photoIdString,
                    'Target folder not found'
                );
            }

            return new BulkMoveResult($moved, $failed);
        }

        foreach ($command->photoIds as $photoIdString) {
            try {
                $photoId = PhotoId::fromString($photoIdString);
                $photo = $this->photoRepository->findById($photoId);

                if ($photo === null) {
                    $failed[] = new BulkOperationFailure(
                        $photoIdString,
                        'Photo not found'
                    );

                    continue;
                }

                if ($photo->folderId()->equals($targetFolderId)) {
                    $failed[] = new BulkOperationFailure(
                        $photoIdString,
                        'Photo already in target folder'
                    );

                    continue;
                }

                $photo->moveToFolder($targetFolderId);
                $this->photoRepository->save($photo);

                $moved[] = $photoIdString;
            } catch (Throwable) {
                $failed[] = new BulkOperationFailure(
                    $photoIdString,
                    'Move operation failed'
                );
            }
        }

        return new BulkMoveResult($moved, $failed);
    }
}
