<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\Request;

use App\Photo\UserInterface\Http\Request\PhotoQueryParams;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversNothing
 */
final class PhotoQueryParamsTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $params = new PhotoQueryParams();

        self::assertSame('uploadedAt', $params->sortBy);
        self::assertSame('desc', $params->sortOrder);
        self::assertNull($params->search);
        self::assertSame([], $params->mimeTypes);
        self::assertSame([], $params->extensions);
        self::assertNull($params->sizeMin);
        self::assertNull($params->sizeMax);
        self::assertNull($params->dateFrom);
        self::assertNull($params->dateTo);
    }

    public function testValidSortByValues(): void
    {
        $validSortBy = ['uploadedAt', 'takenAt', 'fileName', 'sizeInBytes', 'mimeType'];

        foreach ($validSortBy as $sortBy) {
            $params = new PhotoQueryParams(sortBy: $sortBy);
            self::assertSame($sortBy, $params->sortBy);
        }
    }

    public function testInvalidSortByThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sortBy value "invalid"');

        new PhotoQueryParams(sortBy: 'invalid');
    }

    public function testValidSortOrderValues(): void
    {
        $params1 = new PhotoQueryParams(sortOrder: 'asc');
        self::assertSame('asc', $params1->sortOrder);

        $params2 = new PhotoQueryParams(sortOrder: 'desc');
        self::assertSame('desc', $params2->sortOrder);
    }

    public function testInvalidSortOrderThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sortOrder value "ascending"');

        new PhotoQueryParams(sortOrder: 'ascending');
    }

    public function testSizeRangeValidation(): void
    {
        $params = new PhotoQueryParams(sizeMin: 1024, sizeMax: 2048);

        self::assertSame(1024, $params->sizeMin);
        self::assertSame(2048, $params->sizeMax);
    }

    public function testNegativeSizeMinThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sizeMin must be a positive integer');

        new PhotoQueryParams(sizeMin: -100);
    }

    public function testNegativeSizeMaxThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sizeMax must be a positive integer');

        new PhotoQueryParams(sizeMax: -100);
    }

    public function testSizeMinGreaterThanSizeMaxThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sizeMin cannot be greater than sizeMax');

        new PhotoQueryParams(sizeMin: 2048, sizeMax: 1024);
    }

    public function testDateRangeValidation(): void
    {
        $dateFrom = new DateTimeImmutable('2025-01-01');
        $dateTo = new DateTimeImmutable('2025-12-31');

        $params = new PhotoQueryParams(dateFrom: $dateFrom, dateTo: $dateTo);

        self::assertSame($dateFrom, $params->dateFrom);
        self::assertSame($dateTo, $params->dateTo);
    }

    public function testDateFromAfterDateToThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dateFrom cannot be after dateTo');

        new PhotoQueryParams(
            dateFrom: new DateTimeImmutable('2025-12-31'),
            dateTo: new DateTimeImmutable('2025-01-01')
        );
    }

    public function testHasFiltersReturnsFalseWhenNoFilters(): void
    {
        $params = new PhotoQueryParams();

        self::assertFalse($params->hasFilters());
    }

    public function testHasFiltersReturnsTrueWhenSearchProvided(): void
    {
        $params = new PhotoQueryParams(search: 'vacation');

        self::assertTrue($params->hasFilters());
    }

    public function testHasFiltersReturnsTrueWhenMimeTypesProvided(): void
    {
        $params = new PhotoQueryParams(mimeTypes: ['image/jpeg']);

        self::assertTrue($params->hasFilters());
    }

    public function testCountAppliedFiltersReturnsZeroWhenNoFilters(): void
    {
        $params = new PhotoQueryParams();

        self::assertSame(0, $params->countAppliedFilters());
    }

    public function testCountAppliedFiltersCountsAllFilters(): void
    {
        $params = new PhotoQueryParams(
            search: 'vacation',
            mimeTypes: ['image/jpeg'],
            extensions: ['jpg', 'png'],
            sizeMin: 1024,
            dateFrom: new DateTimeImmutable('2025-01-01')
        );

        // search=1, mimeTypes=1, extensions=1, size=1, date=1 = 5
        self::assertSame(5, $params->countAppliedFilters());
    }

    public function testFromRequestWithDefaultValues(): void
    {
        $request = new Request();

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame('uploadedAt', $params->sortBy);
        self::assertSame('desc', $params->sortOrder);
        self::assertNull($params->search);
    }

    public function testFromRequestParsesSortParameters(): void
    {
        $request = new Request(['sortBy' => 'fileName', 'sortOrder' => 'asc']);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame('fileName', $params->sortBy);
        self::assertSame('asc', $params->sortOrder);
    }

    public function testFromRequestParsesSearchParameter(): void
    {
        $request = new Request(['search' => 'beach photos']);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame('beach photos', $params->search);
    }

    public function testFromRequestIgnoresEmptySearchString(): void
    {
        $request = new Request(['search' => '']);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertNull($params->search);
    }

    public function testFromRequestParsesCommaSeparatedMimeTypes(): void
    {
        $request = new Request(['mimeType' => 'image/jpeg,image/png']);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame(['image/jpeg', 'image/png'], $params->mimeTypes);
    }

    public function testFromRequestParsesArrayMimeTypes(): void
    {
        $request = new Request(['mimeType' => ['image/jpeg', 'image/png']]);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame(['image/jpeg', 'image/png'], $params->mimeTypes);
    }

    public function testFromRequestParsesCommaSeparatedExtensions(): void
    {
        $request = new Request(['extension' => 'jpg,png,gif']);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame(['jpg', 'png', 'gif'], $params->extensions);
    }

    public function testFromRequestParsesSizeRange(): void
    {
        $request = new Request(['sizeMin' => '1024', 'sizeMax' => '2048']);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame(1024, $params->sizeMin);
        self::assertSame(2048, $params->sizeMax);
    }

    public function testFromRequestParsesIso8601DateRange(): void
    {
        $request = new Request([
            'dateFrom' => '2025-01-01T00:00:00Z',
            'dateTo' => '2025-12-31T23:59:59Z',
        ]);

        $params = PhotoQueryParams::fromRequest($request);

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

        PhotoQueryParams::fromRequest($request);
    }

    public function testFromRequestHandlesAllParametersTogether(): void
    {
        $request = new Request([
            'sortBy' => 'takenAt',
            'sortOrder' => 'asc',
            'search' => 'sunset',
            'mimeType' => 'image/jpeg,image/png',
            'extension' => 'jpg,png',
            'sizeMin' => '500000',
            'sizeMax' => '5000000',
            'dateFrom' => '2025-06-01T00:00:00Z',
            'dateTo' => '2025-08-31T23:59:59Z',
        ]);

        $params = PhotoQueryParams::fromRequest($request);

        self::assertSame('takenAt', $params->sortBy);
        self::assertSame('asc', $params->sortOrder);
        self::assertSame('sunset', $params->search);
        self::assertSame(['image/jpeg', 'image/png'], $params->mimeTypes);
        self::assertSame(['jpg', 'png'], $params->extensions);
        self::assertSame(500000, $params->sizeMin);
        self::assertSame(5000000, $params->sizeMax);
        self::assertNotNull($params->dateFrom);
        self::assertNotNull($params->dateTo);
        // search=1, mimeTypes=1, extensions=1, size=1, date=1 = 5 filters
        self::assertSame(5, $params->countAppliedFilters());
    }
}
