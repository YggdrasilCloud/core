<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\GetPhotoThumbnail\GetPhotoThumbnailQuery;
use App\Photo\Application\Query\GetPhotoThumbnail\PhotoNotFoundException;
use App\Photo\Application\Query\GetPhotoThumbnail\ThumbnailFileNotFoundException;
use App\Photo\Application\Query\GetPhotoThumbnail\ThumbnailNotFoundException;
use App\Photo\UserInterface\Http\Responder\FileResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetPhotoThumbnailController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private FileResponder $responder,
    ) {}

    #[Route('/api/photos/{photoId}/thumbnail', name: 'get_photo_thumbnail', methods: ['GET', 'HEAD'])]
    public function __invoke(string $photoId, Request $request): Response
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetPhotoThumbnailQuery($photoId));
            $model = $envelope->last(HandledStamp::class)?->getResult();

            return $this->responder->respond($model, $request);
        } catch (HandlerFailedException $e) {
            // Unwrap the original exception from Symfony Messenger
            $originalException = $e->getPrevious();

            if ($originalException instanceof PhotoNotFoundException) {
                return new Response('Photo not found', Response::HTTP_NOT_FOUND);
            }

            if ($originalException instanceof ThumbnailNotFoundException) {
                return new Response('Thumbnail not available', Response::HTTP_NOT_FOUND);
            }

            if ($originalException instanceof ThumbnailFileNotFoundException) {
                return new Response('Thumbnail file not found on disk', Response::HTTP_NOT_FOUND);
            }

            if ($originalException instanceof InvalidArgumentException) {
                return new Response('Invalid photo ID', Response::HTTP_BAD_REQUEST);
            }

            throw $e;
        }
    }
}
