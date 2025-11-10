<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\ListFolders\ListFoldersQuery;
use App\Photo\UserInterface\Http\Request\FolderQueryParams;
use App\Photo\UserInterface\Http\Request\PaginationParams;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListFoldersController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders', name: 'list_folders', methods: ['GET'])]
    public function __invoke(
        PaginationParams $pagination,
        FolderQueryParams $queryParams
    ): Response {
        $envelope = $this->queryBus->dispatch(new ListFoldersQuery(
            $pagination->page,
            $pagination->perPage,
            $queryParams->toCriteria(),
        ));
        $result = $envelope->last(HandledStamp::class)?->getResult();

        $data = array_map(
            static fn ($folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'createdAt' => $folder->createdAt,
                'parentId' => $folder->parentId,
            ],
            $result->items->toArray()
        );

        return $this->responder->success([
            'data' => $data,
            'pagination' => [
                'page' => $result->page,
                'perPage' => $result->perPage,
                'total' => $result->total,
            ],
            'filters' => [
                'sortBy' => $result->criteria->sortBy,
                'sortOrder' => $result->criteria->sortOrder,
                'search' => $result->criteria->search,
                'dateFrom' => $result->criteria->dateFrom?->format(DateTimeInterface::ATOM),
                'dateTo' => $result->criteria->dateTo?->format(DateTimeInterface::ATOM),
                'appliedFilters' => $result->criteria->countAppliedFilters(),
            ],
        ]);
    }
}
