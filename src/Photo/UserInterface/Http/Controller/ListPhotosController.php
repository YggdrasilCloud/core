<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\ListPhotosInFolder\ListPhotosInFolderQuery;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\UserInterface\Http\Request\PaginationParams;
use App\Photo\UserInterface\Http\Responder\JsonResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListPhotosController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private FolderRepositoryInterface $folderRepository,
        private JsonResponder $responder,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/folders/{folderId}/photos', name: 'list_photos', methods: ['GET'])]
    public function __invoke(string $folderId, Request $request): Response
    {
        try {
            $folder = $this->folderRepository->findById(FolderId::fromString($folderId));
            if ($folder === null) {
                return $this->responder->notFound('Folder not found');
            }

            $pagination = PaginationParams::fromRequest($request);

            $result = $this->handle(new ListPhotosInFolderQuery(
                $folderId,
                $pagination->page,
                $pagination->perPage,
            ));

            return $this->responder->success([
                'data' => $result->photos,
                'pagination' => [
                    'page' => $result->page,
                    'perPage' => $result->perPage,
                    'total' => $result->total,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->responder->badRequest('Invalid request', $e->getMessage());
        }
    }
}
