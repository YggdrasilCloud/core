<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\GetPhotoThumbnail\GetPhotoThumbnailQuery;
use App\Photo\Application\Query\GetPhotoThumbnail\PhotoNotFoundException;
use App\Photo\Application\Query\GetPhotoThumbnail\ThumbnailFileNotFoundException;
use App\Photo\Application\Query\GetPhotoThumbnail\ThumbnailNotFoundException;
use App\Photo\UserInterface\Http\Responder\FileResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetPhotoThumbnailController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private FileResponder $responder,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/photos/{photoId}/thumbnail', name: 'get_photo_thumbnail', methods: ['GET', 'HEAD'])]
    public function __invoke(string $photoId, Request $request): Response
    {
        try {
            $model = $this->handle(new GetPhotoThumbnailQuery($photoId));
            return $this->responder->respond($model, $request);
        } catch (PhotoNotFoundException $e) {
            return new Response('Photo not found', Response::HTTP_NOT_FOUND);
        } catch (ThumbnailNotFoundException $e) {
            return new Response('Thumbnail not available', Response::HTTP_NOT_FOUND);
        } catch (ThumbnailFileNotFoundException $e) {
            return new Response('Thumbnail file not found on disk', Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new Response('Invalid photo ID', Response::HTTP_BAD_REQUEST);
        }
    }
}
