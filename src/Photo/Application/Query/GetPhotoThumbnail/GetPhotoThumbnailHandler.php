<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoThumbnail;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Application\Query\GetPhotoFile\FileNotFoundException;
use App\Photo\Application\Query\GetPhotoFile\FileResponseModel;
use App\Photo\Application\Query\GetPhotoFile\PhotoNotFoundException;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPhotoThumbnailHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * @throws PhotoNotFoundException
     * @throws ThumbnailNotFoundException
     * @throws FileNotFoundException
     */
    public function __invoke(GetPhotoThumbnailQuery $query): FileResponseModel
    {
        try {
            $photoId = PhotoId::fromString($query->photoId);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Invalid photo ID format', 0, $e);
        }

        $photo = $this->photoRepository->findById($photoId);
        if ($photo === null) {
            throw new PhotoNotFoundException($photoId->toString());
        }

        // Check if photo has a thumbnail
        $thumbnailKey = $photo->thumbnailKey();
        if ($thumbnailKey === null) {
            throw ThumbnailNotFoundException::forPhoto($photoId->toString());
        }

        // Check if thumbnail file exists in storage
        if (!$this->fileStorage->exists($thumbnailKey)) {
            throw new FileNotFoundException($thumbnailKey);
        }

        return new FileResponseModel(
            storageKey: $thumbnailKey,
            mimeType: 'image/jpeg', // Thumbnails are always JPEG
            cacheMaxAge: 86400, // 24 hours (thumbnails never change)
        );
    }
}
