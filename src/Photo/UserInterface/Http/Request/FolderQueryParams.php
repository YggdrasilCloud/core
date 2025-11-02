<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use App\Photo\Domain\Criteria\FolderCriteria;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

use function in_array;

/**
 * Query parameters for listing, sorting, and filtering folders.
 */
final readonly class FolderQueryParams
{
    /**
     * @param string                 $sortBy    Sort field: name or createdAt
     * @param string                 $sortOrder Sort direction: asc or desc
     * @param null|string            $search    Search term for folder name (case-insensitive substring)
     * @param null|DateTimeImmutable $dateFrom  Filter folders created after this date
     * @param null|DateTimeImmutable $dateTo    Filter folders created before this date
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

    /**
     * Convert HTTP request DTO to Application-layer Criteria.
     */
    public function toCriteria(): FolderCriteria
    {
        return new FolderCriteria(
            sortBy: $this->sortBy,
            sortOrder: $this->sortOrder,
            search: $this->search,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
        );
    }

    /**
     * Extract and normalize query parameters from HTTP request.
     *
     * @throws InvalidArgumentException If parameters are invalid
     */
    public static function fromRequest(Request $request): self
    {
        $sortBy = $request->query->get('sortBy', 'name');
        $sortOrder = $request->query->get('sortOrder', 'asc');
        $search = $request->query->get('search');

        // Parse date range (ISO 8601 format)
        $dateFrom = self::parseDateParam($request->query->get('dateFrom'));
        $dateTo = self::parseDateParam($request->query->get('dateTo'));

        return new self(
            sortBy: is_string($sortBy) ? $sortBy : 'name',
            sortOrder: is_string($sortOrder) ? $sortOrder : 'asc',
            search: is_string($search) && $search !== '' ? $search : null,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateSortBy(string $sortBy): void
    {
        $allowedFields = ['name', 'createdAt'];

        if (!in_array($sortBy, $allowedFields, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid sortBy value "%s". Allowed values: %s',
                    $sortBy,
                    implode(', ', $allowedFields)
                )
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateSortOrder(string $sortOrder): void
    {
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid sortOrder value "%s". Allowed values: asc, desc', $sortOrder)
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateDateRange(?DateTimeImmutable $dateFrom, ?DateTimeImmutable $dateTo): void
    {
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new InvalidArgumentException('dateFrom cannot be after dateTo');
        }
    }

    /**
     * Parse ISO 8601 date string into DateTimeImmutable.
     *
     * @throws InvalidArgumentException If date format is invalid
     */
    private static function parseDateParam(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Date parameter must be a string in ISO 8601 format');
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);

        if ($date === false) {
            // Try fallback format without timezone
            $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        }

        if ($date === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid date format "%s". Expected ISO 8601 format (e.g., 2025-10-30T20:00:00Z)', $value)
            );
        }

        return $date;
    }
}
