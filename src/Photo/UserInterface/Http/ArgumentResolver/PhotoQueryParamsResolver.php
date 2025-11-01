<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\PhotoQueryParams;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class PhotoQueryParamsResolver implements ValueResolverInterface
{
    /**
     * @return iterable<PhotoQueryParams>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== PhotoQueryParams::class) {
            return [];
        }

        yield PhotoQueryParams::fromRequest($request);
    }
}
