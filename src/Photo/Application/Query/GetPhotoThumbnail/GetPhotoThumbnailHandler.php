<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoThumbnail;

use App\Photo\Application\Query\GetPhotoFile\FileResponseModel;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPhotoThumbnailHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private string $storageBasePath,
    ) {}

    /**
     * @throws PhotoNotFoundException
     * @throws ThumbnailNotFoundException
     * @throws ThumbnailFileNotFoundException
     */
    public function __invoke(GetPhotoThumbnailQuery $query): FileResponseModel
    {
        $photo = $this->photoRepository->findById(PhotoId::fromString($query->photoId));

        if ($photo === null) {
            throw new PhotoNotFoundException($query->photoId);
        }

        $thumbnailPath = $photo->storedFile()->thumbnailPath();

        if ($thumbnailPath === null) {
            throw new ThumbnailNotFoundException($query->photoId);
        }

        $fullPath = $this->storageBasePath.'/'.$thumbnailPath;

        if (!file_exists($fullPath)) {
            throw new ThumbnailFileNotFoundException($fullPath);
        }

        return new FileResponseModel(
            filePath: $fullPath,
            mimeType: 'image/jpeg', // Thumbnails are always JPEG
            cacheMaxAge: 31536000, // 1 year (thumbnails never change)
        );
    }
}
