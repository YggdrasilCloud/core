<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\Request\CreateFolderRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class CreateFolderRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @return iterable<CreateFolderRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type !== CreateFolderRequest::class) {
            return [];
        }

        yield CreateFolderRequest::fromRequest($request, $this->serializer);
    }
}
