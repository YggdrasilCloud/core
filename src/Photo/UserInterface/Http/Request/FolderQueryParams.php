<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use DateTimeImmutable;

/**
 * Query parameters for listing, sorting, and filtering folders.
 */
final readonly class FolderQueryParams
{
    /**
     * @param string $sortBy Sort field: name or createdAt
     * @param string $sortOrder Sort direction: asc or desc
     * @param string|null $search Search term for folder name (case-insensitive substring)
     * @param DateTimeImmutable|null $dateFrom Filter folders created after this date
     * @param DateTimeImmutable|null $dateTo Filter folders created before this date
     */
    public function __construct(
        public string $sortBy = 'name',
        public string $sortOrder = 'asc',
        public ?string $search = null,
        public ?DateTimeImmutable $dateFrom = null,
        public ?DateTimeImmutable $dateTo = null,
    ) {
        $this->validateSortBy($sortBy);
        $this->validateSortOrder($sortOrder);
        $this->validateDateRange($dateFrom, $dateTo);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateSortBy(string $sortBy): void
    {
        $allowedFields = ['name', 'createdAt'];

        if (!\in_array($sortBy, $allowedFields, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid sortBy value "%s". Allowed values: %s',
                    $sortBy,
                    implode(', ', $allowedFields)
                )
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateSortOrder(string $sortOrder): void
    {
        if (!\in_array($sortOrder, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid sortOrder value "%s". Allowed values: asc, desc', $sortOrder)
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateDateRange(?DateTimeImmutable $dateFrom, ?DateTimeImmutable $dateTo): void
    {
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new \InvalidArgumentException('dateFrom cannot be after dateTo');
        }
    }

    /**
     * Check if any filters are applied (excluding sort parameters).
     */
    public function hasFilters(): bool
    {
        return $this->search !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    /**
     * Count how many filters are applied.
     */
    public function countAppliedFilters(): int
    {
        $count = 0;

        if ($this->search !== null) {
            ++$count;
        }
        if ($this->dateFrom !== null || $this->dateTo !== null) {
            ++$count;
        }

        return $count;
    }
}
