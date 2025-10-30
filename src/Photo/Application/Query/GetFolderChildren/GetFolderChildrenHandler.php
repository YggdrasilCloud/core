<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderChildren;

use App\Photo\Application\Query\ListFolders\FolderDto;
use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\UserInterface\Http\Request\FolderQueryParams;
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
        $queryParams = $query->queryParams ?? new FolderQueryParams();

        // Validate parent exists
        $parent = $this->folderRepository->findById($parentId);

        if ($parent === null) {
            throw FolderNotFoundException::withId($parentId);
        }

        $offset = max(0, min(PHP_INT_MAX, ($query->page - 1) * $query->perPage));

        $folders = $this->folderRepository->findByParentId(
            $parentId,
            $queryParams,
            $query->perPage,
            $offset,
        );

        $total = $this->folderRepository->countByParentId($parentId, $queryParams);

        $children = array_map(
            static fn ($folder) => new FolderDto(
                id: $folder->id()->toString(),
                name: $folder->name()->toString(),
                createdAt: $folder->createdAt()->format(DateTimeInterface::ATOM),
            ),
            $folders
        );

        return new GetFolderChildrenResult(
            $children,
            $query->page,
            $query->perPage,
            $total,
            $queryParams,
        );
    }
}
