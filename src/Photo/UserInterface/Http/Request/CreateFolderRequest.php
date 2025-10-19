<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

use function count;
use function is_array;
use function is_string;

final readonly class CreateFolderRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Folder name is required')]
        public string $name,
        #[Assert\NotBlank(message: 'Owner ID is required')]
        public string $ownerId,
        public ?string $parentId = null,
    ) {}

    /**
     * Extract, deserialize and validate create folder request data.
     *
     * @throws InvalidArgumentException if validation fails or deserialization fails
     */
    public static function fromRequest(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): self {
        $content = $request->getContent();

        if (empty($content)) {
            throw new InvalidArgumentException('Request body cannot be empty');
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new InvalidArgumentException('Invalid JSON: expected object');
            }

            $name = $data['name'] ?? '';
            $ownerId = $data['ownerId'] ?? '';
            $parentId = $data['parentId'] ?? null;

            if (!is_string($name)) {
                throw new InvalidArgumentException('Field "name" must be a string');
            }

            if (!is_string($ownerId)) {
                throw new InvalidArgumentException('Field "ownerId" must be a string');
            }

            if ($parentId !== null && !is_string($parentId)) {
                throw new InvalidArgumentException('Field "parentId" must be a string or null');
            }

            $dto = new self(
                name: $name,
                ownerId: $ownerId,
                parentId: $parentId,
            );

            // Validate using Symfony Validator constraints
            $violations = $validator->validate($dto);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }

                throw new InvalidArgumentException(implode(', ', $errors));
            }

            return $dto;
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Request validation failed: '.$e->getMessage(), 0, $e);
        }
    }
}
