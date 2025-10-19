<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderChildren;

use App\Photo\Application\Query\ListFolders\FolderDto;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use DateTimeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetFolderChildrenHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(GetFolderChildrenQuery $query): GetFolderChildrenResult
    {
        $parentId = FolderId::fromString($query->parentId);
        $folders = $this->folderRepository->findByParentId($parentId);

        $children = array_map(
            static fn ($folder) => new FolderDto(
                id: $folder->id()->toString(),
                name: $folder->name()->toString(),
                createdAt: $folder->createdAt()->format(DateTimeInterface::ATOM),
            ),
            $folders
        );

        return new GetFolderChildrenResult($children);
    }
}
