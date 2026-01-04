<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

/**
 * Value object representing the result of a recursive folder deletion.
 */
final readonly class DeletedContent
{
    public function __construct(
        public int $photosDeleted,
        public int $subfoldersDeleted,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            $this->photosDeleted + $other->photosDeleted,
            $this->subfoldersDeleted + $other->subfoldersDeleted,
        );
    }

    public static function empty(): self
    {
        return new self(0, 0);
    }
}
