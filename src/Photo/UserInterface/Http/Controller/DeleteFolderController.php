<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\DeleteFolder\DeleteFolderCommand;
use App\Photo\Domain\Model\DeletedContent;
use App\Photo\UserInterface\Http\Request\DeleteFolderRequest;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for folder deletion.
 *
 * Supports recursive deletion via ?recursive=true query parameter.
 * Domain exceptions (FolderNotFound, FolderNotEmpty) are handled by ExceptionSubscriber.
 */
final readonly class DeleteFolderController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{folderId}', name: 'delete_folder', methods: ['DELETE'])]
    public function __invoke(string $folderId, DeleteFolderRequest $request): JsonResponse
    {
        $envelope = $this->commandBus->dispatch(new DeleteFolderCommand(
            $folderId,
            $request->recursive,
        ));

        /** @var DeletedContent $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();

        return $this->responder->success([
            'deleted' => [
                'folderId' => $folderId,
                'photosDeleted' => $result->photosDeleted,
                'subfoldersDeleted' => $result->subfoldersDeleted,
            ],
        ]);
    }
}
