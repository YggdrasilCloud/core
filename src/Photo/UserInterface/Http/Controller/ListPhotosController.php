<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\ListPhotosInFolder\ListPhotosInFolderQuery;
use App\Photo\Application\Query\ListPhotosInFolder\ListPhotosInFolderResult;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListPhotosController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private FolderRepositoryInterface $folderRepository,
    ) {
    }

    #[Route('/api/folders/{folderId}/photos', name: 'list_photos', methods: ['GET'])]
    public function __invoke(string $folderId, Request $request): Response
    {
        try {
            // Verify folder exists before listing photos
            $folder = $this->folderRepository->findById(FolderId::fromString($folderId));
            if ($folder === null) {
                return new JsonResponse(['error' => 'Folder not found'], Response::HTTP_NOT_FOUND);
            }

            // Normalize and validate HTTP input parameters
            // Controller validates user input; Query/Handler can be reused from other contexts
            // Max perPage = 100 to prevent excessive database load
            $page = max(1, (int) $request->query->get('page', 1));
            $perPage = min(100, max(1, (int) $request->query->get('perPage', 20)));

            $envelope = $this->queryBus->dispatch(new ListPhotosInFolderQuery(
                $folderId,
                $page,
                $perPage,
            ));

            /** @var HandledStamp|null $handledStamp */
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return new JsonResponse(['error' => 'Query not handled'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            /** @var ListPhotosInFolderResult $result */
            $result = $handledStamp->getResult();

            return new JsonResponse([
                'data' => $result->photos,
                'pagination' => [
                    'page' => $result->page,
                    'perPage' => $result->perPage,
                    'total' => $result->total,
                ],
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
