<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\UploadPhotoToFolder;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Domain\Service\FileCollisionResolver;
use App\Photo\Application\Port\ThumbnailServiceInterface;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use DomainException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function sprintf;

#[AsMessageHandler]
final readonly class UploadPhotoToFolderHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private FolderRepositoryInterface $folderRepository,
        private FileStorageInterface $fileStorage,
        private ThumbnailServiceInterface $thumbnailService,
        private FileSystemPathBuilder $pathBuilder,
        private FileCollisionResolver $collisionResolver,
    ) {}

    public function __invoke(UploadPhotoToFolderCommand $command): void
    {
        $folderId = FolderId::fromString($command->folderId);

        // Vérifier que le dossier existe
        $folder = $this->folderRepository->findById($folderId);
        if ($folder === null) {
            // NOTE: DomainException indicates business rule violation (folder must exist)
            // Future: consider custom FolderNotFoundException for better error handling
            throw new DomainException(sprintf('Folder not found: %s', $command->folderId));
        }

        // Build human-readable storage path based on folder hierarchy
        $photoId = PhotoId::fromString($command->photoId);
        $proposedPath = $this->pathBuilder->buildFilePath($folder, $command->fileName);
        $storageKey = $this->collisionResolver->resolveUniquePath($proposedPath);

        $storedObject = $this->fileStorage->save(
            $command->fileStream,
            $storageKey,
            $command->mimeType,
            $command->sizeInBytes
        );

        // Generate the thumbnail (handles both local and remote storage)
        $thumbnailKey = $this->thumbnailService->generateThumbnail($storedObject, $command->mimeType);

        // Create the Photo entity
        $photo = Photo::upload(
            $photoId,
            $folderId,
            UserId::fromString($command->ownerId),
            FileName::fromString($command->fileName),
            $storedObject->key,
            $storedObject->adapter,
            $command->mimeType,
            $command->sizeInBytes,
            $thumbnailKey,
        );

        $this->photoRepository->save($photo);
    }
}
