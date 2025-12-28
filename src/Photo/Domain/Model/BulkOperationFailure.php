<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

/**
 * Value object representing a failure during a bulk operation.
 *
 * Captures which item failed and why, enabling detailed error reporting
 * for partial success scenarios in bulk operations.
 */
final readonly class BulkOperationFailure
{
    public function __construct(
        public string $photoId,
        public string $reason,
    ) {}

    /**
     * @return array{photoId: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'photoId' => $this->photoId,
            'reason' => $this->reason,
        ];
    }
}
