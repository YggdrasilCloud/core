<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\BulkDeletePhotos\BulkDeletePhotos;
use App\Photo\Application\Command\BulkDeletePhotos\BulkDeleteResult;
use App\Photo\UserInterface\Http\Request\BulkDeletePhotosRequest;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

use function array_map;

/**
 * Controller for bulk photo deletion.
 *
 * Uses POST instead of DELETE because DELETE with request body is non-standard HTTP.
 * Returns 200 with detailed results including successful deletions and failures.
 */
final readonly class BulkDeletePhotosController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/photos/bulk-delete', name: 'api_photos_bulk_delete', methods: ['POST'])]
    public function __invoke(BulkDeletePhotosRequest $request): JsonResponse
    {
        try {
            $envelope = $this->commandBus->dispatch(
                new BulkDeletePhotos($request->photoIds)
            );

            /** @var BulkDeleteResult $result */
            $result = $envelope->last(HandledStamp::class)?->getResult();

            if ($result->allFailed()) {
                return $this->responder->error(
                    'Bulk delete failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'All photo deletions failed',
                    [
                        'deleted' => [],
                        'failed' => array_map(
                            static fn ($failure) => $failure->toArray(),
                            $result->failed
                        ),
                        'summary' => [
                            'total' => $result->totalCount(),
                            'deletedCount' => 0,
                            'failedCount' => $result->failedCount(),
                        ],
                    ]
                );
            }

            return $this->responder->success([
                'deleted' => $result->deleted,
                'failed' => array_map(
                    static fn ($failure) => $failure->toArray(),
                    $result->failed
                ),
                'summary' => [
                    'total' => $result->totalCount(),
                    'deletedCount' => $result->deletedCount(),
                    'failedCount' => $result->failedCount(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responder->badRequest($e->getMessage());
        }
    }
}
