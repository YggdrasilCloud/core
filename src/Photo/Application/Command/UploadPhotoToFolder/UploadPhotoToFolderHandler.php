<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\UploadPhotoToFolder;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Domain\Service\ThumbnailGenerator;
use DomainException;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function sprintf;

#[AsMessageHandler]
final readonly class UploadPhotoToFolderHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private FolderRepositoryInterface $folderRepository,
        private FileStorageInterface $fileStorage,
        private ThumbnailGenerator $thumbnailGenerator,
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

        // Store the file with the new FileStorageInterface
        $photoId = PhotoId::fromString($command->photoId);
        $storageKey = sprintf('photos/%s/%s', $folderId->toString(), $photoId->toString());

        $storedObject = $this->fileStorage->save(
            $command->fileStream,
            $storageKey,
            $command->mimeType,
            $command->sizeInBytes
        );

        // Generate the thumbnail
        $thumbnailKey = null;

        try {
            $thumbnailPath = $this->thumbnailGenerator->generateThumbnail($storedObject->key);
            // Use the actual generated thumbnail path as the key
            $thumbnailKey = $thumbnailPath;
        } catch (Exception) {
            // If generation fails, continue without thumbnail
            // Future improvement: log the error
        }

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
