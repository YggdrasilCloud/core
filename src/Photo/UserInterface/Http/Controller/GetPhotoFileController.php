<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Query\GetPhotoFile\FileNotFoundException;
use App\Photo\Application\Query\GetPhotoFile\GetPhotoFileQuery;
use App\Photo\Application\Query\GetPhotoFile\PhotoNotFoundException;
use App\Photo\UserInterface\Http\Responder\FileResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetPhotoFileController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private FileResponder $responder,
    ) {
    }

    #[Route('/api/photos/{photoId}/file', name: 'get_photo_file', methods: ['GET', 'HEAD'])]
    public function __invoke(string $photoId, Request $request): Response
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetPhotoFileQuery($photoId));
            $model = $envelope->last(HandledStamp::class)?->getResult();

            return $this->responder->respond($model, $request);
        } catch (PhotoNotFoundException $e) {
            return new Response('Photo not found', Response::HTTP_NOT_FOUND);
        } catch (FileNotFoundException $e) {
            return new Response('File not found on disk', Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new Response('Invalid photo ID', Response::HTTP_BAD_REQUEST);
        }
    }
}
