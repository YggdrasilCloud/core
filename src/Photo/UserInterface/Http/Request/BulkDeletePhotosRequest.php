<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

use function array_map;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Request DTO for bulk photo deletion.
 *
 * Validates that photoIds is a non-empty array of valid UUIDs.
 * Limits the number of photos per request to prevent timeouts.
 */
final readonly class BulkDeletePhotosRequest
{
    private const int MAX_PHOTOS_PER_REQUEST = 100;
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @param list<string> $photoIds
     */
    private function __construct(
        public array $photoIds,
    ) {}

    /**
     * @throws InvalidArgumentException if validation fails
     */
    public static function fromRequest(Request $request): self
    {
        $content = $request->getContent();

        if (empty($content)) {
            throw new InvalidArgumentException('Request body cannot be empty');
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON: '.$e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON: expected object');
        }

        $photoIds = $data['photoIds'] ?? null;

        if (!is_array($photoIds)) {
            throw new InvalidArgumentException('Field "photoIds" must be an array');
        }

        if (count($photoIds) === 0) {
            throw new InvalidArgumentException('Field "photoIds" cannot be empty');
        }

        if (count($photoIds) > self::MAX_PHOTOS_PER_REQUEST) {
            throw new InvalidArgumentException(sprintf(
                'Cannot process more than %d photos at once',
                self::MAX_PHOTOS_PER_REQUEST
            ));
        }

        /** @var list<string> $validatedIds */
        $validatedIds = array_map(static function (mixed $id, int $index): string {
            if (!is_string($id)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid photo ID at index %d: must be a string',
                    $index
                ));
            }

            if (!preg_match(self::UUID_PATTERN, $id)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid photo ID format at index %d: %s',
                    $index,
                    $id
                ));
            }

            return $id;
        }, $photoIds, array_keys($photoIds));

        return new self($validatedIds);
    }
}
