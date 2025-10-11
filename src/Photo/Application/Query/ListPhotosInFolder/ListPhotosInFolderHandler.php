<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListPhotosInFolder;

use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPhotosInFolderHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
    ) {
    }

    public function __invoke(ListPhotosInFolderQuery $query): ListPhotosInFolderResult
    {
        $folderId = FolderId::fromString($query->folderId);

        // Protect against integer overflow for very high page numbers
        // max(0, ...) ensures offset is never negative
        $offset = max(0, min(PHP_INT_MAX, ($query->page - 1) * $query->perPage));

        $photos = $this->photoRepository->findByFolderId(
            $folderId,
            $query->perPage,
            $offset,
        );

        $total = $this->photoRepository->countByFolderId($folderId);

        $photoDtos = array_map(
            static fn ($photo) => new PhotoDto(
                $photo->id()->toString(),
                $photo->fileName()->toString(),
                $photo->storedFile()->storagePath(),
                $photo->storedFile()->mimeType(),
                $photo->storedFile()->sizeInBytes(),
                $photo->uploadedAt()->format(\DateTimeInterface::ATOM),
                '/api/photos/' . $photo->id()->toString() . '/file',
                $photo->storedFile()->thumbnailPath() !== null
                    ? '/api/photos/' . $photo->id()->toString() . '/thumbnail'
                    : null,
            ),
            $photos,
        );

        return new ListPhotosInFolderResult(
            $photoDtos,
            $query->page,
            $query->perPage,
            $total,
        );
    }
}
