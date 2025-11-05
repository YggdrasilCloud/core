<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Command\UploadPhotoToFolder\UploadPhotoToFolderCommand;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Service\FileValidator;
use App\Photo\UserInterface\Http\Request\UploadPhotoRequest;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use DomainException;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

use function sprintf;

final readonly class UploadPhotoController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private FileValidator $fileValidator,
        private JsonResponder $responder,
    ) {}

    #[Route('/api/folders/{folderId}/photos', name: 'upload_photo', methods: ['POST'])]
    public function __invoke(string $folderId, UploadPhotoRequest $uploadRequest): Response
    {
        try {
            $validationError = $this->fileValidator->validate($uploadRequest->file);
            if ($validationError !== null) {
                return $this->responder->badRequest($validationError);
            }

            $mimeType = $uploadRequest->file->getMimeType();
            if ($mimeType === null) {
                return $this->responder->badRequest('Unable to determine file type');
            }

            $sanitizedFileName = $this->fileValidator->sanitizeFilename(
                $uploadRequest->file->getClientOriginalName()
            );

            $photoId = PhotoId::generate();

            $fileStream = fopen($uploadRequest->file->getPathname(), 'r');
            if ($fileStream === false) {
                return $this->responder->serverError('Failed to read uploaded file');
            }

            try {
                $this->commandBus->dispatch(new UploadPhotoToFolderCommand(
                    $photoId->toString(),
                    $folderId,
                    $uploadRequest->ownerId,
                    $sanitizedFileName,
                    $fileStream,
                    $mimeType,
                    $uploadRequest->file->getSize(),
                ));
            } finally {
                fclose($fileStream);
            }

            return $this->responder->created([
                'id' => $photoId->toString(),
                'folderId' => $folderId,
                'fileName' => $sanitizedFileName,
                'mimeType' => $mimeType,
                'size' => $uploadRequest->file->getSize(),
            ], sprintf('/api/folders/%s/photos', $folderId));
        } catch (HandlerFailedException $e) {
            // Unwrap the original exception thrown by the handler
            $originalException = $e->getPrevious() ?? $e;

            if ($originalException instanceof DomainException) {
                return $this->responder->notFound($originalException->getMessage());
            }

            if ($originalException instanceof InvalidArgumentException) {
                return $this->responder->badRequest($originalException->getMessage());
            }

            return $this->responder->serverError('Failed to upload photo');
        } catch (DomainException $e) {
            return $this->responder->notFound($e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responder->badRequest($e->getMessage());
        } catch (Exception) {
            return $this->responder->serverError('Failed to upload photo');
        }
    }
}
