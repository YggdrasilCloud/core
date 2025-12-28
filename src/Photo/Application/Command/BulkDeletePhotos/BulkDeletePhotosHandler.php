<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\BulkDeletePhotos;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Domain\Model\BulkOperationFailure;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Handles bulk photo deletion.
 *
 * Processes each photo individually, allowing partial success.
 * Successfully deleted photos are removed from both storage and database.
 * Failed deletions are collected with reasons and returned in the result.
 */
#[AsMessageHandler]
final readonly class BulkDeletePhotosHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    public function __invoke(BulkDeletePhotos $command): BulkDeleteResult
    {
        $deleted = [];
        $failed = [];

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

                // Delete from storage first (main file + thumbnail if exists)
                $this->fileStorage->delete($photo->storageKey());

                if ($photo->thumbnailKey() !== null) {
                    $this->fileStorage->delete($photo->thumbnailKey());
                }

                // Then delete from database
                $this->photoRepository->remove($photo);

                $deleted[] = $photoIdString;
            } catch (Throwable) {
                $failed[] = new BulkOperationFailure(
                    $photoIdString,
                    'Delete operation failed'
                );
            }
        }

        return new BulkDeleteResult($deleted, $failed);
    }
}
