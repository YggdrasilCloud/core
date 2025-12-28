<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\BulkDeletePhotos;

use App\Photo\Domain\Model\BulkOperationFailure;

/**
 * Result of a bulk delete operation.
 *
 * Provides detailed information about which photos were successfully deleted
 * and which failed, enabling partial success scenarios.
 */
final readonly class BulkDeleteResult
{
    /**
     * @param list<string>               $deleted IDs of successfully deleted photos
     * @param list<BulkOperationFailure> $failed  Details of failed deletions
     */
    public function __construct(
        public array $deleted,
        public array $failed,
    ) {}

    public function totalCount(): int
    {
        return count($this->deleted) + count($this->failed);
    }

    public function deletedCount(): int
    {
        return count($this->deleted);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function allFailed(): bool
    {
        return $this->deletedCount() === 0 && $this->failedCount() > 0;
    }

    public function allSucceeded(): bool
    {
        return $this->failedCount() === 0 && $this->deletedCount() > 0;
    }
}
