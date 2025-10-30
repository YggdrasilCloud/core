<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\FolderQueryParams;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class FolderQueryParamsResolver implements ValueResolverInterface
{
    /**
     * @return iterable<FolderQueryParams>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== FolderQueryParams::class) {
            return [];
        }

        yield FolderQueryParams::fromRequest($request);
    }
}
