<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderCommand;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Service\FileValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UploadPhotoController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private FileValidator $fileValidator,
    ) {
    }

    #[Route('/api/folders/{folderId}/photos', name: 'upload_photo', methods: ['POST'])]
    public function __invoke(string $folderId, Request $request): Response
    {
        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('photo');

        if ($uploadedFile === null) {
            return new JsonResponse(['error' => 'Missing required file: photo'], Response::HTTP_BAD_REQUEST);
        }

        $ownerId = $request->request->get('ownerId');
        if ($ownerId === null) {
            return new JsonResponse(['error' => 'Missing required field: ownerId'], Response::HTTP_BAD_REQUEST);
        }

        // Validate file size and type
        $validationError = $this->fileValidator->validate($uploadedFile);
        if ($validationError !== null) {
            return new JsonResponse(['error' => $validationError], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $uploadedFile->getMimeType();
        if ($mimeType === null) {
            return new JsonResponse(['error' => 'Unable to determine file type'], Response::HTTP_BAD_REQUEST);
        }

        // Sanitize filename
        $sanitizedFileName = $this->fileValidator->sanitizeFilename(
            $uploadedFile->getClientOriginalName()
        );

        $photoId = PhotoId::generate();
        $fileStream = null;

        // Future enhancement: detect duplicate uploads by content hash (SHA-256)
        // - Calculate hash before upload
        // - Check if photo with same hash exists in folder
        // - Return 409 Conflict with existing photo ID if duplicate found
        // This prevents unnecessary storage and provides deduplication

        try {
            $fileStream = fopen($uploadedFile->getPathname(), 'r');
            if ($fileStream === false) {
                return new JsonResponse(['error' => 'Failed to read uploaded file'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            try {
                $this->commandBus->dispatch(new UploadPhotoToFolderCommand(
                    $photoId->toString(),
                    $folderId,
                    $ownerId,
                    $sanitizedFileName,
                    $fileStream,
                    $mimeType,
                    $uploadedFile->getSize(),
                ));
            } finally {
                // Ensure file handle is always closed, even if command fails
                if ($fileStream !== null) {
                    fclose($fileStream);
                }
            }

            $response = new JsonResponse([
                'id' => $photoId->toString(),
                'folderId' => $folderId,
                'fileName' => $sanitizedFileName,
                'mimeType' => $mimeType,
                'size' => $uploadedFile->getSize(),
            ], Response::HTTP_CREATED);

            // Add Location header pointing to the folder's photos list
            $response->headers->set('Location', sprintf('/api/folders/%s/photos', $folderId));

            return $response;
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to upload photo'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
