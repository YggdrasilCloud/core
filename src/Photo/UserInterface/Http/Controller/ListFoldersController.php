<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\ListFolders\ListFoldersQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListFoldersController
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    #[Route('/api/folders', name: 'list_folders', methods: ['GET'])]
    public function __invoke(): Response
    {
        $envelope = $this->queryBus->dispatch(new ListFoldersQuery());

        /** @var \App\Photo\Application\Query\ListFolders\ListFoldersResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();

        $data = array_map(
            fn ($folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'createdAt' => $folder->createdAt,
            ],
            $result->items
        );

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
