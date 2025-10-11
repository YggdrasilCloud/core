<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Responder;

use App\Photo\Application\Query\GetPhotoFile\FileResponseModel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class FileResponder
{
    public function respond(FileResponseModel $model, Request $request): BinaryFileResponse
    {
        $response = new BinaryFileResponse($model->filePath);
        $response->headers->set('Content-Type', $model->mimeType);

        // Enable conditional cache with ETag and Last-Modified
        $response->setAutoEtag();
        $response->setAutoLastModified();
        $response->setPublic();
        $response->setMaxAge($model->cacheMaxAge);

        // Check if response is not modified (sends 304 if client has fresh cache)
        $response->isNotModified($request);

        return $response;
    }
}
