<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\PaginationParams;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class PaginationParamsResolver implements ValueResolverInterface
{
    /**
     * @return iterable<PaginationParams>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== PaginationParams::class) {
            return [];
        }

        yield PaginationParams::fromRequest($request);
    }
}
