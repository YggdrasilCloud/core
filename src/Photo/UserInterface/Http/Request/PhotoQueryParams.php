<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Request;

use DateTimeImmutable;

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
}
