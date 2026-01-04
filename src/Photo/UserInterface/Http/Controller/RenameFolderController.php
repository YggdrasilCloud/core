<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\RenameFolder\RenameFolderCommand;
use App\Photo\UserInterface\Http\Request\RenameFolderRequest;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RenameFolderController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{folderId}', name: 'rename_folder', methods: ['PATCH'])]
    public function __invoke(string $folderId, RenameFolderRequest $request): Response
    {
        try {
            $this->commandBus->dispatch(new RenameFolderCommand(
                $folderId,
                $request->name,
            ));

            return $this->responder->success([
                'id' => $folderId,
                'name' => $request->name,
            ]);
        } catch (DomainException $e) {
            return $this->responder->notFound($e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responder->badRequest($e->getMessage());
        }
    }
}
