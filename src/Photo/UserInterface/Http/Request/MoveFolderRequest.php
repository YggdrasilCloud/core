<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

use const JSON_THROW_ON_ERROR;

final readonly class MoveFolderRequest
{
    public function __construct(
        public ?string $targetParentId,
    ) {}

    /**
     * Extract and deserialize move folder request data.
     *
     * @throws InvalidArgumentException if deserialization fails
     */
    public static function fromRequest(Request $request): self
    {
        $content = $request->getContent();

        if (empty($content)) {
            throw new InvalidArgumentException('Request body cannot be empty');
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new InvalidArgumentException('Invalid JSON: expected object');
            }

            // targetParentId can be null (move to root) or a string (move to specific parent)
            $targetParentId = $data['targetParentId'] ?? null;

            if ($targetParentId !== null && !is_string($targetParentId)) {
                throw new InvalidArgumentException('Field "targetParentId" must be a string or null');
            }

            return new self(targetParentId: $targetParentId);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON: '.$e->getMessage(), 0, $e);
        }
    }
}
