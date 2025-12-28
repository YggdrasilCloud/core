<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\BulkMovePhotos;

use App\Photo\Domain\Model\BulkOperationFailure;

/**
 * Result of a bulk move operation.
 *
 * Provides detailed information about which photos were successfully moved
 * and which failed, enabling partial success scenarios.
 */
final readonly class BulkMoveResult
{
    /**
     * @param list<string>               $moved  IDs of successfully moved photos
     * @param list<BulkOperationFailure> $failed Details of failed moves
     */
    public function __construct(
        public array $moved,
        public array $failed,
    ) {}

    public function totalCount(): int
    {
        return count($this->moved) + count($this->failed);
    }

    public function movedCount(): int
    {
        return count($this->moved);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function allFailed(): bool
    {
        return $this->movedCount() === 0 && $this->failedCount() > 0;
    }

    public function allSucceeded(): bool
    {
        return $this->failedCount() === 0 && $this->movedCount() > 0;
    }
}
