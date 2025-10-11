<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Domain\Model\PhotoId;
use App\Photo\Infrastructure\Persistence\Doctrine\Repository\DoctrinePhotoRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetPhotoFileController
{
    public function __construct(
        private DoctrinePhotoRepository $photoRepository,
        private string $storageBasePath,
    ) {
    }

    #[Route('/api/photos/{photoId}/file', name: 'get_photo_file', methods: ['GET', 'HEAD'])]
    public function __invoke(string $photoId): Response
    {
        try {
            $photo = $this->photoRepository->findById(PhotoId::fromString($photoId));

            if ($photo === null) {
                return new Response('Photo not found', Response::HTTP_NOT_FOUND);
            }

            // Construct full file path: base path + relative storage path
            $relativePath = $photo->storedFile()->storagePath();
            $fullPath = $this->storageBasePath . '/' . $relativePath;

            if (!file_exists($fullPath)) {
                return new Response('File not found on disk', Response::HTTP_NOT_FOUND);
            }

            $response = new BinaryFileResponse($fullPath);
            $response->headers->set('Content-Type', $photo->storedFile()->mimeType());

            return $response;
        } catch (\InvalidArgumentException $e) {
            return new Response('Invalid photo ID', Response::HTTP_BAD_REQUEST);
        }
    }
}
