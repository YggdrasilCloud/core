<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use Countable;
use IteratorAggregate;
use Traversable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;

/**
 * Collection of MIME types.
 *
 * Immutable collection that ensures all MIME types are valid.
 *
 * @implements IteratorAggregate<int, MimeType>
 */
final readonly class MimeTypeCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<MimeType> $mimeTypes
     */
    private function __construct(
        private array $mimeTypes,
    ) {}

    /**
     * Create collection from array of strings.
     *
     * @param list<string> $mimeTypes
     */
    public static function fromStrings(array $mimeTypes): self
    {
        // Filter out empty strings (from CSV parsing, etc.)
        $filtered = array_filter($mimeTypes, static fn (string $type): bool => trim($type) !== '');

        // Convert to MimeType VOs
        $vos = array_map(
            static fn (string $type): MimeType => MimeType::fromString($type),
            $filtered
        );

        return new self(array_values($vos));
    }

    /**
     * Create empty collection.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Check if collection contains the given MIME type.
     */
    public function contains(string $mimeType): bool
    {
        foreach ($this->mimeTypes as $type) {
            if ($type->toString() === $mimeType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->mimeTypes) === 0;
    }

    /**
     * Get collection as array of strings.
     *
     * @return list<string>
     */
    public function toStrings(): array
    {
        return array_map(
            static fn (MimeType $type): string => $type->toString(),
            $this->mimeTypes
        );
    }

    /**
     * Get collection as comma-separated string.
     */
    public function toCommaSeparatedString(): string
    {
        return implode(', ', $this->toStrings());
    }

    public function count(): int
    {
        return count($this->mimeTypes);
    }

    /**
     * @return Traversable<int, MimeType>
     */
    public function getIterator(): Traversable
    {
        yield from $this->mimeTypes;
    }
}
