<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\GetFolderChildren\GetFolderChildrenQuery;
use App\Photo\Application\Query\GetFolderChildren\GetFolderChildrenResult;
use App\Photo\UserInterface\Http\Request\FolderQueryParams;
use App\Photo\UserInterface\Http\Request\PaginationParams;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetFolderChildrenController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{id}/children', name: 'get_folder_children', methods: ['GET'])]
    public function __invoke(
        string $id,
        PaginationParams $pagination,
        FolderQueryParams $queryParams
    ): Response {
        try {
            $envelope = $this->queryBus->dispatch(new GetFolderChildrenQuery(
                $id,
                $pagination->page,
                $pagination->perPage,
                $queryParams->toCriteria(),
            ));

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);

            /** @var GetFolderChildrenResult $result */
            $result = $stamp->getResult();

            return $this->responder->success([
                'data' => array_map(
                    static fn ($child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'createdAt' => $child->createdAt,
                    ],
                    $result->children
                ),
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
        } catch (InvalidArgumentException $e) {
            return $this->responder->notFound($e->getMessage());
        }
    }
}
