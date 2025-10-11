<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\CreateFolder\CreateFolderCommand;
use App\Photo\Domain\Model\FolderId;
use App\Photo\UserInterface\Http\Request\CreateFolderRequest;
use App\Photo\UserInterface\Http\Responder\JsonResponder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CreateFolderController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders', name: 'create_folder', methods: ['POST'])]
    public function __invoke(CreateFolderRequest $request): Response
    {
        try {
            $folderId = FolderId::generate();

            $this->commandBus->dispatch(new CreateFolderCommand(
                $folderId->toString(),
                $request->name,
                $request->ownerId,
            ));

            return $this->responder->created([
                'id' => $folderId->toString(),
                'name' => $request->name,
                'ownerId' => $request->ownerId,
            ], \sprintf('/api/folders/%s', $folderId->toString()));
        } catch (\InvalidArgumentException $e) {
            return $this->responder->badRequest($e->getMessage());
        }
    }
}
