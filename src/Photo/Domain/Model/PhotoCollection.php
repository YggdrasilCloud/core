<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use Countable;
use IteratorAggregate;
use Traversable;

use function array_map;
use function count;

/**
 * Collection of Photos.
 *
 * Immutable collection for type-safe photo lists.
 *
 * @implements IteratorAggregate<int, Photo>
 */
final readonly class PhotoCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<Photo> $photos
     */
    private function __construct(
        private array $photos,
    ) {}

    /**
     * Create collection from array of Photo entities.
     *
     * @param list<Photo> $photos
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
     * @return list<Photo>
     */
    public function toArray(): array
    {
        return $this->photos;
    }

    /**
     * Map photos to another type.
     *
     * @template T
     *
     * @param callable(Photo): T $callback
     *
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->photos);
    }

    public function count(): int
    {
        return count($this->photos);
    }

    /**
     * @return Traversable<int, Photo>
     */
    public function getIterator(): Traversable
    {
        yield from $this->photos;
    }
}
