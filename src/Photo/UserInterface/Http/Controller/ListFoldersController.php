<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\ListFolders\ListFoldersQuery;
use App\Photo\UserInterface\Http\Responder\JsonResponder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListFoldersController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private JsonResponder $responder,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/folders', name: 'list_folders', methods: ['GET'])]
    public function __invoke(): Response
    {
        $result = $this->handle(new ListFoldersQuery());

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
