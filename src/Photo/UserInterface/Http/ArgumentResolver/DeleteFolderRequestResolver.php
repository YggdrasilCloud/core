<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\DeleteFolderRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class DeleteFolderRequestResolver implements ValueResolverInterface
{
    /**
     * @return iterable<DeleteFolderRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== DeleteFolderRequest::class) {
            return [];
        }

        yield DeleteFolderRequest::fromRequest($request);
    }
}
