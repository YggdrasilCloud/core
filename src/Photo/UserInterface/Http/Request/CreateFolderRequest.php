<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CreateFolderRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Folder name is required')]
        public string $name,

        #[Assert\NotBlank(message: 'Owner ID is required')]
        public string $ownerId,
    ) {
    }

    /**
     * Extract, deserialize and validate create folder request data.
     *
     * @throws \InvalidArgumentException if validation fails or deserialization fails
     */
    public static function fromRequest(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): self {
        $content = $request->getContent();

        if (empty($content)) {
            throw new \InvalidArgumentException('Request body cannot be empty');
        }

        try {
            /** @var self $dto */
            $dto = $serializer->deserialize($content, self::class, 'json');

            // Validate using Symfony Validator constraints
            $violations = $validator->validate($dto);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                throw new \InvalidArgumentException(implode(', ', $errors));
            }

            return $dto;
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }
    }
}
