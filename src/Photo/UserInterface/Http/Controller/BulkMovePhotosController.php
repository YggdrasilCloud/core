<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\BulkMovePhotos\BulkMovePhotos;
use App\Photo\Application\Command\BulkMovePhotos\BulkMoveResult;
use App\Photo\UserInterface\Http\Request\BulkMovePhotosRequest;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

use function array_map;

/**
 * Controller for bulk photo movement.
 *
 * Uses PATCH as it partially updates photo resources (changing their folder).
 * Returns 200 with detailed results including successful moves and failures.
 */
final readonly class BulkMovePhotosController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/photos/bulk-move', name: 'api_photos_bulk_move', methods: ['PATCH'])]
    public function __invoke(BulkMovePhotosRequest $request): JsonResponse
    {
        try {
            $envelope = $this->commandBus->dispatch(
                new BulkMovePhotos($request->photoIds, $request->targetFolderId)
            );

            /** @var BulkMoveResult $result */
            $result = $envelope->last(HandledStamp::class)?->getResult();

            if ($result->allFailed()) {
                return $this->responder->error(
                    'Bulk move failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'All photo moves failed',
                    [
                        'moved' => [],
                        'failed' => array_map(
                            static fn ($failure) => $failure->toArray(),
                            $result->failed
                        ),
                        'summary' => [
                            'total' => $result->totalCount(),
                            'movedCount' => 0,
                            'failedCount' => $result->failedCount(),
                        ],
                    ]
                );
            }

            return $this->responder->success([
                'moved' => $result->moved,
                'failed' => array_map(
                    static fn ($failure) => $failure->toArray(),
                    $result->failed
                ),
                'summary' => [
                    'total' => $result->totalCount(),
                    'movedCount' => $result->movedCount(),
                    'failedCount' => $result->failedCount(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responder->badRequest($e->getMessage());
        }
    }
}
