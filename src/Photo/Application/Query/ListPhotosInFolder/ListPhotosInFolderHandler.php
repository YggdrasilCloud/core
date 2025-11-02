<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListPhotosInFolder;

use App\Photo\Domain\Criteria\PhotoCriteria;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use DateTimeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPhotosInFolderHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepository,
    ) {}

    public function __invoke(ListPhotosInFolderQuery $query): ListPhotosInFolderResult
    {
        $folderId = FolderId::fromString($query->folderId);
        $criteria = $query->criteria ?? new PhotoCriteria();

        // Protect against integer overflow for very high page numbers
        // max(0, ...) ensures offset is never negative
        $offset = max(0, min(PHP_INT_MAX, ($query->page - 1) * $query->perPage));

        $photos = $this->photoRepository->findByFolderId(
            $folderId,
            $criteria,
            $query->perPage,
            $offset,
        );

        $total = $this->photoRepository->countByFolderId($folderId, $criteria);

        $photoDtos = array_map(
            static fn ($photo) => new PhotoDto(
                $photo->id()->toString(),
                $photo->fileName()->toString(),
                $photo->storageKey(),
                $photo->mimeType(),
                $photo->sizeInBytes(),
                $photo->uploadedAt()->format(DateTimeInterface::ATOM),
                $photo->takenAt()?->format(DateTimeInterface::ATOM),
                '/api/photos/'.$photo->id()->toString().'/file',
                $photo->thumbnailKey() !== null
                    ? '/api/photos/'.$photo->id()->toString().'/thumbnail'
                    : null,
            ),
            $photos,
        );

        return new ListPhotosInFolderResult(
            $photoDtos,
            $query->page,
            $query->perPage,
            $total,
            $criteria,
        );
    }
}
