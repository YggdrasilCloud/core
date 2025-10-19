<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\GetFolderChildren\GetFolderChildrenQuery;
use App\Photo\Application\Query\GetFolderChildren\GetFolderChildrenResult;
use App\Photo\UserInterface\Http\Responder\JsonResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetFolderChildrenController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{id}/children', name: 'get_folder_children', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetFolderChildrenQuery($id));

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);

            /** @var GetFolderChildrenResult $result */
            $result = $stamp->getResult();

            return $this->responder->success([
                'children' => array_map(
                    static fn ($child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'createdAt' => $child->createdAt,
                    ],
                    $result->children
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responder->notFound($e->getMessage());
        }
    }
}
