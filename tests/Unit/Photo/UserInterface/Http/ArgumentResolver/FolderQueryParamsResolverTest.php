<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\ArgumentResolver\FolderQueryParamsResolver;
use App\Photo\UserInterface\Http\Request\FolderQueryParams;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 *
 * @coversNothing
 */
final class FolderQueryParamsResolverTest extends TestCase
{
    private FolderQueryParamsResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new FolderQueryParamsResolver();
    }

    public function testResolveReturnsEmptyArrayWhenTypeDoesNotMatch(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('SomeOtherType');

        $result = $this->resolver->resolve($request, $argument);

        self::assertSame([], iterator_to_array($result));
    }

    public function testResolveYieldsFolderQueryParamsWhenTypeMatches(): void
    {
        $request = new Request([
            'search' => 'vacation',
            'sortBy' => 'name',
            'sortOrder' => 'desc',
        ]);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(FolderQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(FolderQueryParams::class, $items[0]);
        self::assertSame('vacation', $items[0]->search);
        self::assertSame('name', $items[0]->sortBy);
        self::assertSame('desc', $items[0]->sortOrder);
    }

    public function testResolvePropagatesExceptionFromFolderQueryParams(): void
    {
        $request = new Request(['sortBy' => 'invalid_field']);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(FolderQueryParams::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sortBy');

        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    public function testResolveWithDefaultValues(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(FolderQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(FolderQueryParams::class, $items[0]);
        self::assertNull($items[0]->search);
        self::assertSame('name', $items[0]->sortBy);
        self::assertSame('asc', $items[0]->sortOrder);
    }

    public function testResolveWithDateRange(): void
    {
        $request = new Request([
            'dateFrom' => '2024-01-01T00:00:00Z',
            'dateTo' => '2024-12-31T23:59:59Z',
        ]);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(FolderQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(FolderQueryParams::class, $items[0]);
        self::assertInstanceOf(DateTimeImmutable::class, $items[0]->dateFrom);
        self::assertInstanceOf(DateTimeImmutable::class, $items[0]->dateTo);
    }
}
