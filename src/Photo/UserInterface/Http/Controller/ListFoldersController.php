<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\ListFolders\ListFoldersQuery;
use App\Photo\UserInterface\Http\Responder\JsonResponder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListFoldersController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private JsonResponder $responder,
    ) {
    }

    #[Route('/api/folders', name: 'list_folders', methods: ['GET'])]
    public function __invoke(): Response
    {
        $envelope = $this->queryBus->dispatch(new ListFoldersQuery());
        $result = $envelope->last(HandledStamp::class)?->getResult();

        $data = array_map(
            fn ($folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'createdAt' => $folder->createdAt,
            ],
            $result->items
        );

        return $this->responder->success($data);
    }
}
