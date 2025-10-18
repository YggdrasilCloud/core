<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\UploadPhotoToFolder;

use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\StoredFile;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Port\FileStorageInterface;
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

        // Stocker le fichier
        $storagePath = $this->fileStorage->store($command->fileStream, $command->fileName);

        // Générer la vignette
        $thumbnailPath = null;

        try {
            $thumbnailPath = $this->thumbnailGenerator->generateThumbnail($storagePath);
        } catch (Exception) {
            // Si la génération échoue, on continue sans vignette
            // Amélioration future : logger l'erreur
        }

        // Créer l'entité Photo
        $photo = Photo::upload(
            PhotoId::fromString($command->photoId),
            $folderId,
            UserId::fromString($command->ownerId),
            FileName::fromString($command->fileName),
            StoredFile::create($storagePath, $command->mimeType, $command->sizeInBytes, $thumbnailPath),
        );

        $this->photoRepository->save($photo);
    }
}
