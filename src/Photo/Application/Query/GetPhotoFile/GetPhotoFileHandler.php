<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoFile;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPhotoFileHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * @throws PhotoNotFoundException
     * @throws FileNotFoundException
     */
    public function __invoke(GetPhotoFileQuery $query): FileResponseModel
    {
        $photo = $this->photoRepository->findById(PhotoId::fromString($query->photoId));

        if ($photo === null) {
            throw new PhotoNotFoundException($query->photoId);
        }

        $storageKey = $photo->storageKey();

        if (!$this->fileStorage->exists($storageKey)) {
            throw new FileNotFoundException($storageKey);
        }

        return new FileResponseModel(
            storageKey: $storageKey,
            mimeType: $photo->mimeType(),
            cacheMaxAge: 3600, // 1 hour
        );
    }
}
