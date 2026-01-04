<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\MoveFolder\MoveFolderCommand;
use App\Photo\UserInterface\Http\Request\MoveFolderRequest;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MoveFolderController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{folderId}/move', name: 'move_folder', methods: ['PATCH'])]
    public function __invoke(string $folderId, MoveFolderRequest $request): Response
    {
        try {
            $this->commandBus->dispatch(new MoveFolderCommand(
                $folderId,
                $request->targetParentId,
            ));

            return $this->responder->success([
                'id' => $folderId,
                'parentId' => $request->targetParentId,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responder->badRequest($e->getMessage());
        }
    }
}
