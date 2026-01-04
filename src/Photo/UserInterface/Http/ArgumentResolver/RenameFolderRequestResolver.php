<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\RenameFolderRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RenameFolderRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    /**
     * @return iterable<RenameFolderRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== RenameFolderRequest::class) {
            return [];
        }

        yield RenameFolderRequest::fromRequest($request, $this->validator);
    }
}
