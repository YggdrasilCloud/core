<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\MoveFolderRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class MoveFolderRequestResolver implements ValueResolverInterface
{
    /**
     * @return iterable<MoveFolderRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== MoveFolderRequest::class) {
            return [];
        }

        yield MoveFolderRequest::fromRequest($request);
    }
}
