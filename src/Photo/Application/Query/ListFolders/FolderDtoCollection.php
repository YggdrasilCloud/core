<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListFolders;

use Countable;
use IteratorAggregate;
use Traversable;

use function count;

/**
 * Collection of FolderDto objects.
 *
 * Immutable collection for type-safe folder DTO lists.
 *
 * @implements IteratorAggregate<int, FolderDto>
 */
final readonly class FolderDtoCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<FolderDto> $folders
     */
    private function __construct(
        private array $folders,
    ) {}

    /**
     * Create collection from array of FolderDto.
     *
     * @param list<FolderDto> $folders
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
     * @return list<FolderDto>
     */
    public function toArray(): array
    {
        return $this->folders;
    }

    public function count(): int
    {
        return count($this->folders);
    }

    /**
     * @return Traversable<int, FolderDto>
     */
    public function getIterator(): Traversable
    {
        yield from $this->folders;
    }
}
