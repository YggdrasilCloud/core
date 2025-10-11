<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Domain\Model\PhotoId;
use App\Photo\Infrastructure\Persistence\Doctrine\Repository\DoctrinePhotoRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetPhotoThumbnailController
{
    public function __construct(
        private DoctrinePhotoRepository $photoRepository,
        private string $storageBasePath,
    ) {
    }

    #[Route('/api/photos/{photoId}/thumbnail', name: 'get_photo_thumbnail', methods: ['GET', 'HEAD'])]
    public function __invoke(string $photoId): Response
    {
        try {
            $photo = $this->photoRepository->findById(PhotoId::fromString($photoId));

            if ($photo === null) {
                return new Response('Photo not found', Response::HTTP_NOT_FOUND);
            }

            $thumbnailPath = $photo->storedFile()->thumbnailPath();

            if ($thumbnailPath === null) {
                return new Response('Thumbnail not available', Response::HTTP_NOT_FOUND);
            }

            // Construct full file path: base path + relative thumbnail path
            $fullPath = $this->storageBasePath . '/' . $thumbnailPath;

            if (!file_exists($fullPath)) {
                return new Response('Thumbnail file not found on disk', Response::HTTP_NOT_FOUND);
            }

            $response = new BinaryFileResponse($fullPath);
            // Les thumbnails sont toujours des JPEG
            $response->headers->set('Content-Type', 'image/jpeg');
            // Cache pour 1 an (les thumbnails ne changent jamais)
            $response->setMaxAge(31536000);
            $response->setSharedMaxAge(31536000);

            return $response;
        } catch (\InvalidArgumentException $e) {
            return new Response('Invalid photo ID', Response::HTTP_BAD_REQUEST);
        }
    }
}
