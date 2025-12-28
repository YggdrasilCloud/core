<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\BulkDeletePhotosRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class BulkDeletePhotosRequestResolver implements ValueResolverInterface
{
    /**
     * @return iterable<BulkDeletePhotosRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== BulkDeletePhotosRequest::class) {
            return [];
        }

        yield BulkDeletePhotosRequest::fromRequest($request);
    }
}
