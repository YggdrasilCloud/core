<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\GetFolderPath\GetFolderPathQuery;
use App\Photo\Application\Query\GetFolderPath\GetFolderPathResult;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetFolderPathController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{id}/path', name: 'get_folder_path', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetFolderPathQuery($id));

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);

            /** @var GetFolderPathResult $result */
            $result = $stamp->getResult();

            return $this->responder->success([
                'path' => array_map(
                    static fn ($segment) => [
                        'id' => $segment->id,
                        'name' => $segment->name,
                    ],
                    $result->path
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responder->notFound($e->getMessage());
        }
    }
}
