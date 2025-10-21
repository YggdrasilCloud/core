<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Responder;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Application\Query\GetPhotoFile\FileResponseModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class FileResponder
{
    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {}

    public function respond(FileResponseModel $model, Request $request): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->headers->set('Content-Type', $model->mimeType);

        // Set callback to stream file content
        $response->setCallback(function () use ($model): void {
            $stream = $this->fileStorage->readStream($model->storageKey);
            fpassthru($stream);
            fclose($stream);
        });

        $response->setPublic();
        $response->setMaxAge($model->cacheMaxAge);
        $response->setSharedMaxAge($model->cacheMaxAge);

        // For conditional cache, we'd need to compute ETag from storage metadata
        // This is a future enhancement when storage supports metadata queries

        return $response;
    }
}
