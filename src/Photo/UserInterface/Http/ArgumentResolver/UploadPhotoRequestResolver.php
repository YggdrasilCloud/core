<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\UploadPhotoRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class UploadPhotoRequestResolver implements ValueResolverInterface
{
    /**
     * @return iterable<UploadPhotoRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== UploadPhotoRequest::class) {
            return [];
        }

        yield UploadPhotoRequest::fromRequest($request);
    }
}
