<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function count;
use function implode;
use function is_array;
use function is_string;

use const JSON_THROW_ON_ERROR;

final readonly class RenameFolderRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Folder name is required')]
        #[Assert\Length(max: 255, maxMessage: 'Folder name cannot exceed 255 characters')]
        public string $name,
    ) {}

    /**
     * Extract, deserialize and validate rename folder request data.
     *
     * @throws InvalidArgumentException if validation fails or deserialization fails
     */
    public static function fromRequest(
        Request $request,
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

            if (!is_string($name)) {
                throw new InvalidArgumentException('Field "name" must be a string');
            }

            $dto = new self(name: $name);

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
        }
    }
}
