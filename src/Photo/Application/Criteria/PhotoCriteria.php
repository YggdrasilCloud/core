<?php

declare(strict_types=1);

namespace App\Photo\Application\Criteria;

use DateTimeImmutable;
use InvalidArgumentException;

use function in_array;

/**
 * Criteria for listing, sorting, and filtering photos.
 * Domain-level DTO with no framework dependencies.
 *
 * Important behavior notes:
 * - When sortBy='takenAt', the repository uses COALESCE(takenAt, uploadedAt) for sorting.
 *   This ensures photos without EXIF capture date fall back to upload date.
 * - Date filters (dateFrom/dateTo) are applied on COALESCE(takenAt, uploadedAt), providing
 *   consistent date-based filtering regardless of whether takenAt is available.
 */
final readonly class PhotoCriteria
{
    /**
     * @param string                 $sortBy     Sort field: uploadedAt, takenAt, fileName, sizeInBytes, mimeType
     * @param string                 $sortOrder  Sort direction: asc or desc
     * @param null|string            $search     Search term for filename (case-insensitive substring)
     * @param list<string>           $mimeTypes  Filter by MIME types (e.g., ["image/jpeg", "image/png"])
     * @param list<string>           $extensions Filter by file extensions (e.g., ["jpg", "png"])
     * @param null|int               $sizeMin    Minimum file size in bytes
     * @param null|int               $sizeMax    Maximum file size in bytes
     * @param null|DateTimeImmutable $dateFrom   Filter photos after this date (uses COALESCE(takenAt, uploadedAt))
     * @param null|DateTimeImmutable $dateTo     Filter photos before this date (uses COALESCE(takenAt, uploadedAt))
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
     * @throws InvalidArgumentException
     */
    private function validateSortBy(string $sortBy): void
    {
        $allowedFields = ['uploadedAt', 'takenAt', 'fileName', 'sizeInBytes', 'mimeType'];

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
    private function validateSizeRange(?int $sizeMin, ?int $sizeMax): void
    {
        if ($sizeMin !== null && $sizeMin < 0) {
            throw new InvalidArgumentException('sizeMin must be a positive integer');
        }

        if ($sizeMax !== null && $sizeMax < 0) {
            throw new InvalidArgumentException('sizeMax must be a positive integer');
        }

        if ($sizeMin !== null && $sizeMax !== null && $sizeMin > $sizeMax) {
            throw new InvalidArgumentException('sizeMin cannot be greater than sizeMax');
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
}
