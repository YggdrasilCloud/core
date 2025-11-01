<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\Request;

use App\Photo\UserInterface\Http\Request\FolderQueryParams;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @coversNothing
 */
final class FolderQueryParamsTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $params = new FolderQueryParams();

        self::assertSame('name', $params->sortBy);
        self::assertSame('asc', $params->sortOrder);
        self::assertNull($params->search);
        self::assertNull($params->dateFrom);
        self::assertNull($params->dateTo);
    }

    public function testValidSortByValues(): void
    {
        $validSortBy = ['name', 'createdAt'];

        foreach ($validSortBy as $sortBy) {
            $params = new FolderQueryParams(sortBy: $sortBy);
            self::assertSame($sortBy, $params->sortBy);
        }
    }

    public function testInvalidSortByThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sortBy value "invalid"');

        new FolderQueryParams(sortBy: 'invalid');
    }

    public function testValidSortOrderValues(): void
    {
        $params1 = new FolderQueryParams(sortOrder: 'asc');
        self::assertSame('asc', $params1->sortOrder);

        $params2 = new FolderQueryParams(sortOrder: 'desc');
        self::assertSame('desc', $params2->sortOrder);
    }

    public function testInvalidSortOrderThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sortOrder value "ascending"');

        new FolderQueryParams(sortOrder: 'ascending');
    }

    public function testDateRangeValidation(): void
    {
        $dateFrom = new DateTimeImmutable('2025-01-01');
        $dateTo = new DateTimeImmutable('2025-12-31');

        $params = new FolderQueryParams(dateFrom: $dateFrom, dateTo: $dateTo);

        self::assertSame($dateFrom, $params->dateFrom);
        self::assertSame($dateTo, $params->dateTo);
    }

    public function testDateFromAfterDateToThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dateFrom cannot be after dateTo');

        new FolderQueryParams(
            dateFrom: new DateTimeImmutable('2025-12-31'),
            dateTo: new DateTimeImmutable('2025-01-01')
        );
    }

    public function testHasFiltersReturnsFalseWhenNoFilters(): void
    {
        $params = new FolderQueryParams();

        self::assertFalse($params->hasFilters());
    }

    public function testHasFiltersReturnsTrueWhenSearchProvided(): void
    {
        $params = new FolderQueryParams(search: 'vacation');

        self::assertTrue($params->hasFilters());
    }

    public function testHasFiltersReturnsTrueWhenDateFromProvided(): void
    {
        $params = new FolderQueryParams(dateFrom: new DateTimeImmutable('2025-01-01'));

        self::assertTrue($params->hasFilters());
    }

    public function testHasFiltersReturnsTrueWhenDateToProvided(): void
    {
        $params = new FolderQueryParams(dateTo: new DateTimeImmutable('2025-12-31'));

        self::assertTrue($params->hasFilters());
    }

    public function testCountAppliedFiltersReturnsZeroWhenNoFilters(): void
    {
        $params = new FolderQueryParams();

        self::assertSame(0, $params->countAppliedFilters());
    }

    public function testCountAppliedFiltersCountsSearchAsSeparateFilter(): void
    {
        $params = new FolderQueryParams(search: 'vacation');

        self::assertSame(1, $params->countAppliedFilters());
    }

    public function testCountAppliedFiltersCountsDateRangeAsOneFilter(): void
    {
        $params = new FolderQueryParams(
            dateFrom: new DateTimeImmutable('2025-01-01'),
            dateTo: new DateTimeImmutable('2025-12-31')
        );

        // Date range is counted as one filter
        self::assertSame(1, $params->countAppliedFilters());
    }

    public function testCountAppliedFiltersCountsDateFromAloneAsOneFilter(): void
    {
        $params = new FolderQueryParams(dateFrom: new DateTimeImmutable('2025-01-01'));

        self::assertSame(1, $params->countAppliedFilters());
    }

    public function testCountAppliedFiltersCountsAllFilters(): void
    {
        $params = new FolderQueryParams(
            search: 'vacation',
            dateFrom: new DateTimeImmutable('2025-01-01')
        );

        // search=1, date=1 = 2
        self::assertSame(2, $params->countAppliedFilters());
    }

    public function testFromRequestWithDefaultValues(): void
    {
        $request = new Request();

        $params = FolderQueryParams::fromRequest($request);

        self::assertSame('name', $params->sortBy);
        self::assertSame('asc', $params->sortOrder);
        self::assertNull($params->search);
        self::assertNull($params->dateFrom);
        self::assertNull($params->dateTo);
    }

    public function testFromRequestParsesSortParameters(): void
    {
        $request = new Request(['sortBy' => 'createdAt', 'sortOrder' => 'desc']);

        $params = FolderQueryParams::fromRequest($request);

        self::assertSame('createdAt', $params->sortBy);
        self::assertSame('desc', $params->sortOrder);
    }

    public function testFromRequestParsesSearchParameter(): void
    {
        $request = new Request(['search' => 'holiday photos']);

        $params = FolderQueryParams::fromRequest($request);

        self::assertSame('holiday photos', $params->search);
    }

    public function testFromRequestIgnoresEmptySearchString(): void
    {
        $request = new Request(['search' => '']);

        $params = FolderQueryParams::fromRequest($request);

        self::assertNull($params->search);
    }

    public function testFromRequestParsesIso8601DateRange(): void
    {
        $request = new Request([
            'dateFrom' => '2025-01-01T00:00:00Z',
            'dateTo' => '2025-12-31T23:59:59Z',
        ]);

        $params = FolderQueryParams::fromRequest($request);

        self::assertInstanceOf(DateTimeImmutable::class, $params->dateFrom);
        self::assertInstanceOf(DateTimeImmutable::class, $params->dateTo);
        self::assertSame('2025-01-01', $params->dateFrom->format('Y-m-d'));
        self::assertSame('2025-12-31', $params->dateTo->format('Y-m-d'));
    }

    public function testFromRequestThrowsExceptionForInvalidDateFormat(): void
    {
        $request = new Request(['dateFrom' => 'invalid-date']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format "invalid-date"');

        FolderQueryParams::fromRequest($request);
    }

    public function testFromRequestHandlesAllParametersTogether(): void
    {
        $request = new Request([
            'sortBy' => 'createdAt',
            'sortOrder' => 'desc',
            'search' => 'family',
            'dateFrom' => '2025-06-01T00:00:00Z',
            'dateTo' => '2025-08-31T23:59:59Z',
        ]);

        $params = FolderQueryParams::fromRequest($request);

        self::assertSame('createdAt', $params->sortBy);
        self::assertSame('desc', $params->sortOrder);
        self::assertSame('family', $params->search);
        self::assertNotNull($params->dateFrom);
        self::assertNotNull($params->dateTo);
        self::assertSame(2, $params->countAppliedFilters());
    }

    public function testFromRequestHandlesDateWithoutTimezone(): void
    {
        $request = new Request(['dateFrom' => '2025-10-30T20:00:00']);

        $params = FolderQueryParams::fromRequest($request);

        self::assertInstanceOf(DateTimeImmutable::class, $params->dateFrom);
        self::assertSame('2025-10-30', $params->dateFrom->format('Y-m-d'));
    }

    public function testFromRequestIgnoresNonStringSearchParameter(): void
    {
        $request = new Request(['search' => 123]);

        $params = FolderQueryParams::fromRequest($request);

        self::assertNull($params->search);
    }
}
