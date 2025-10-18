<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListFolders;

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
        $folders = $this->folderRepository->findAll();

        $items = array_map(
            static fn ($folder) => new FolderDto(
                id: $folder->id()->toString(),
                name: $folder->name()->toString(),
                createdAt: $folder->createdAt()->format(DateTimeInterface::ATOM),
            ),
            $folders
        );

        return new ListFoldersResult($items);
    }
}
