<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\ListPhotosInFolder\ListPhotosInFolderQuery;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\UserInterface\Http\Request\PaginationParams;
use App\Photo\UserInterface\Http\Request\PhotoQueryParams;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListPhotosController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private FolderRepositoryInterface $folderRepository,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{folderId}/photos', name: 'list_photos', methods: ['GET'])]
    public function __invoke(
        string $folderId,
        PaginationParams $pagination,
        PhotoQueryParams $queryParams
    ): Response {
        try {
            $folder = $this->folderRepository->findById(FolderId::fromString($folderId));
            if ($folder === null) {
                return $this->responder->notFound('Folder not found');
            }

            $envelope = $this->queryBus->dispatch(new ListPhotosInFolderQuery(
                $folderId,
                $pagination->page,
                $pagination->perPage,
                $queryParams->toCriteria(),
            ));

            /** @var \App\Photo\Application\Query\ListPhotosInFolder\ListPhotosInFolderResult $result */
            $result = $envelope->last(HandledStamp::class)?->getResult();

            return $this->responder->success([
                'data' => $result->photos->toArray(),
                'pagination' => [
                    'page' => $result->page,
                    'perPage' => $result->perPage,
                    'total' => $result->total,
                ],
                'filters' => [
                    'sortBy' => $result->criteria->sortBy,
                    'sortOrder' => $result->criteria->sortOrder,
                    'search' => $result->criteria->search,
                    'mimeTypes' => $result->criteria->mimeTypes,
                    'extensions' => $result->criteria->extensions,
                    'sizeMin' => $result->criteria->sizeMin,
                    'sizeMax' => $result->criteria->sizeMax,
                    'dateFrom' => $result->criteria->dateFrom?->format(DateTimeInterface::ATOM),
                    'dateTo' => $result->criteria->dateTo?->format(DateTimeInterface::ATOM),
                    'appliedFilters' => $result->criteria->countAppliedFilters(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responder->badRequest('Invalid request', $e->getMessage());
        }
    }
}
