<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\BulkMovePhotosRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class BulkMovePhotosRequestResolver implements ValueResolverInterface
{
    /**
     * @return iterable<BulkMovePhotosRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== BulkMovePhotosRequest::class) {
            return [];
        }

        yield BulkMovePhotosRequest::fromRequest($request);
    }
}
