<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use Countable;
use IteratorAggregate;
use Traversable;

use function array_map;
use function count;

/**
 * Collection of Folders.
 *
 * Immutable collection for type-safe folder lists.
 *
 * @implements IteratorAggregate<int, Folder>
 */
final readonly class FolderCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<Folder> $folders
     */
    private function __construct(
        private array $folders,
    ) {}

    /**
     * Create collection from array of Folder entities.
     *
     * @param list<Folder> $folders
     */
    public static function fromArray(array $folders): self
    {
        return new self($folders);
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
        return count($this->folders) === 0;
    }

    /**
     * Get collection as array.
     *
     * @return list<Folder>
     */
    public function toArray(): array
    {
        return $this->folders;
    }

    /**
     * Map folders to another type.
     *
     * @template T
     *
     * @param callable(Folder): T $callback
     *
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->folders);
    }

    public function count(): int
    {
        return count($this->folders);
    }

    /**
     * @return Traversable<int, Folder>
     */
    public function getIterator(): Traversable
    {
        yield from $this->folders;
    }
}
