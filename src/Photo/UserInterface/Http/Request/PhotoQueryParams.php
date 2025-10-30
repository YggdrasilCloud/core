<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Query parameters for listing, sorting, and filtering photos.
 */
final readonly class PhotoQueryParams
{
    /**
     * @param string $sortBy Sort field: uploadedAt, takenAt, fileName, sizeInBytes, mimeType
     * @param string $sortOrder Sort direction: asc or desc
     * @param string|null $search Search term for filename (case-insensitive substring)
     * @param list<string> $mimeTypes Filter by MIME types (e.g., ["image/jpeg", "image/png"])
     * @param list<string> $extensions Filter by file extensions (e.g., ["jpg", "png"])
     * @param int|null $sizeMin Minimum file size in bytes
     * @param int|null $sizeMax Maximum file size in bytes
     * @param DateTimeImmutable|null $dateFrom Filter photos taken/uploaded after this date
     * @param DateTimeImmutable|null $dateTo Filter photos taken/uploaded before this date
     */
    public function __construct(
        public string $sortBy = 'uploadedAt',
        public string $sortOrder = 'desc',
        public ?string $search = null,
        public array $mimeTypes = [],
        public array $extensions = [],
        public ?int $sizeMin = null,
        public ?int $sizeMax = null,
        public ?DateTimeImmutable $dateFrom = null,
        public ?DateTimeImmutable $dateTo = null,
    ) {
        $this->validateSortBy($sortBy);
        $this->validateSortOrder($sortOrder);
        $this->validateSizeRange($sizeMin, $sizeMax);
        $this->validateDateRange($dateFrom, $dateTo);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateSortBy(string $sortBy): void
    {
        $allowedFields = ['uploadedAt', 'takenAt', 'fileName', 'sizeInBytes', 'mimeType'];

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
    private function validateSizeRange(?int $sizeMin, ?int $sizeMax): void
    {
        if ($sizeMin !== null && $sizeMin < 0) {
            throw new \InvalidArgumentException('sizeMin must be a positive integer');
        }

        if ($sizeMax !== null && $sizeMax < 0) {
            throw new \InvalidArgumentException('sizeMax must be a positive integer');
        }

        if ($sizeMin !== null && $sizeMax !== null && $sizeMin > $sizeMax) {
            throw new \InvalidArgumentException('sizeMin cannot be greater than sizeMax');
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
            || $this->mimeTypes !== []
            || $this->extensions !== []
            || $this->sizeMin !== null
            || $this->sizeMax !== null
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
        if ($this->mimeTypes !== []) {
            ++$count;
        }
        if ($this->extensions !== []) {
            ++$count;
        }
        if ($this->sizeMin !== null || $this->sizeMax !== null) {
            ++$count;
        }
        if ($this->dateFrom !== null || $this->dateTo !== null) {
            ++$count;
        }

        return $count;
    }

    /**
     * Extract and normalize query parameters from HTTP request.
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public static function fromRequest(Request $request): self
    {
        $sortBy = $request->query->get('sortBy', 'uploadedAt');
        $sortOrder = $request->query->get('sortOrder', 'desc');
        $search = $request->query->get('search');

        // Parse array parameters
        $mimeTypes = self::parseArrayParam($request->query->get('mimeType'));
        $extensions = self::parseArrayParam($request->query->get('extension'));

        // Parse size range
        $sizeMin = $request->query->get('sizeMin');
        $sizeMax = $request->query->get('sizeMax');

        // Parse date range (ISO 8601 format)
        $dateFrom = self::parseDateParam($request->query->get('dateFrom'));
        $dateTo = self::parseDateParam($request->query->get('dateTo'));

        return new self(
            sortBy: is_string($sortBy) ? $sortBy : 'uploadedAt',
            sortOrder: is_string($sortOrder) ? $sortOrder : 'desc',
            search: is_string($search) && $search !== '' ? $search : null,
            mimeTypes: $mimeTypes,
            extensions: $extensions,
            sizeMin: is_numeric($sizeMin) ? (int) $sizeMin : null,
            sizeMax: is_numeric($sizeMax) ? (int) $sizeMax : null,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );
    }

    /**
     * Parse comma-separated string or array into list of strings.
     *
     * @return list<string>
     */
    private static function parseArrayParam(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }

    /**
     * Parse ISO 8601 date string into DateTimeImmutable.
     *
     * @throws \InvalidArgumentException If date format is invalid
     */
    private static function parseDateParam(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('Date parameter must be a string in ISO 8601 format');
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);

        if ($date === false) {
            // Try fallback format without timezone
            $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        }

        if ($date === false) {
            throw new \InvalidArgumentException(
                sprintf('Invalid date format "%s". Expected ISO 8601 format (e.g., 2025-10-30T20:00:00Z)', $value)
            );
        }

        return $date;
    }
}
