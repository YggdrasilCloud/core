<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\CreateFolder\CreateFolderCommand;
use App\Photo\Domain\Model\FolderId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CreateFolderController
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    #[Route('/api/folders', name: 'create_folder', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return new JsonResponse(['error' => 'Missing required field: name'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['ownerId'])) {
            return new JsonResponse(['error' => 'Missing required field: ownerId'], Response::HTTP_BAD_REQUEST);
        }

        $folderId = FolderId::generate();

        try {
            $this->commandBus->dispatch(new CreateFolderCommand(
                $folderId->toString(),
                $data['name'],
                $data['ownerId'],
            ));

            return new JsonResponse([
                'id' => $folderId->toString(),
                'name' => $data['name'],
                'ownerId' => $data['ownerId'],
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
