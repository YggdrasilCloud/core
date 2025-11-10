<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListPhotosInFolder;

use Countable;
use IteratorAggregate;
use Traversable;

use function count;

/**
 * Collection of PhotoDto objects.
 *
 * Immutable collection for type-safe photo DTO lists.
 *
 * @implements IteratorAggregate<int, PhotoDto>
 */
final readonly class PhotoDtoCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<PhotoDto> $photos
     */
    private function __construct(
        private array $photos,
    ) {}

    /**
     * Create collection from array of PhotoDto.
     *
     * @param list<PhotoDto> $photos
     */
    public static function fromArray(array $photos): self
    {
        return new self($photos);
    }

    /**
     * Create empty collection.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->photos) === 0;
    }

    /**
     * Get collection as array.
     *
     * @return list<PhotoDto>
     */
    public function toArray(): array
    {
        return $this->photos;
    }

    public function count(): int
    {
        return count($this->photos);
    }

    /**
     * @return Traversable<int, PhotoDto>
     */
    public function getIterator(): Traversable
    {
        yield from $this->photos;
    }
}
