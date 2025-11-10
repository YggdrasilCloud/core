<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListFolders;

use App\Photo\Domain\Criteria\FolderCriteria;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use DateTimeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListFoldersHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(ListFoldersQuery $query): ListFoldersResult
    {
        $criteria = $query->criteria ?? new FolderCriteria();
        $offset = max(0, min(PHP_INT_MAX, ($query->page - 1) * $query->perPage));

        $folders = $this->folderRepository->findAll($criteria, $query->perPage, $offset);
        $total = $this->folderRepository->count($criteria);

        $items = FolderDtoCollection::fromArray(
            array_map(
                static fn ($folder) => new FolderDto(
                    id: $folder->id()->toString(),
                    name: $folder->name()->toString(),
                    createdAt: $folder->createdAt()->format(DateTimeInterface::ATOM),
                    parentId: $folder->parentId()?->toString(),
                ),
                $folders
            )
        );

        return new ListFoldersResult(
            $items,
            $query->page,
            $query->perPage,
            $total,
            $criteria,
        );
    }
}
