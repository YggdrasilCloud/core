<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoFile;

use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPhotoFileHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
        private string $storageBasePath,
    ) {
    }

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

        $relativePath = $photo->storedFile()->storagePath();
        $fullPath = $this->storageBasePath . '/' . $relativePath;

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        return new FileResponseModel(
            filePath: $fullPath,
            mimeType: $photo->storedFile()->mimeType(),
            cacheMaxAge: 3600, // 1 hour
        );
    }
}
