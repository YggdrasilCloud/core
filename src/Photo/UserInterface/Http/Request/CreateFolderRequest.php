<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class CreateFolderRequest
{
    public function __construct(
        public string $name,
        public string $ownerId,
    ) {
    }

    /**
     * Extract and deserialize create folder request data using Symfony Serializer.
     *
     * @throws \InvalidArgumentException if required fields are missing or deserialization fails
     */
    public static function fromRequest(Request $request, SerializerInterface $serializer): self
    {
        $content = $request->getContent();

        if (empty($content)) {
            throw new \InvalidArgumentException('Request body cannot be empty');
        }

        try {
            /** @var self $dto */
            $dto = $serializer->deserialize($content, self::class, 'json');

            // Validate required fields
            if (empty($dto->name)) {
                throw new \InvalidArgumentException('Missing required field: name');
            }

            if (empty($dto->ownerId)) {
                throw new \InvalidArgumentException('Missing required field: ownerId');
            }

            return $dto;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }
    }
}
