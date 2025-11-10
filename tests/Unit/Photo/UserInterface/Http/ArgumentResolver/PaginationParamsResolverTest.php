<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\ArgumentResolver\PaginationParamsResolver;
use App\Photo\UserInterface\Http\Request\PaginationParams;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @coversNothing
 */
final class PaginationParamsResolverTest extends TestCase
{
    private PaginationParamsResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PaginationParamsResolver();
    }

    public function testResolveReturnsEmptyArrayWhenTypeDoesNotMatch(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('SomeOtherType');

        $result = $this->resolver->resolve($request, $argument);

        self::assertSame([], iterator_to_array($result));
    }

    public function testResolveYieldsPaginationParamsWhenTypeMatches(): void
    {
        $request = new Request(['page' => '2', 'perPage' => '50']);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PaginationParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(PaginationParams::class, $items[0]);
        self::assertSame(2, $items[0]->page);
        self::assertSame(50, $items[0]->perPage);
    }

    public function testResolveNormalizesInvalidPageToOne(): void
    {
        $request = new Request(['page' => 'invalid']);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PaginationParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertSame(1, $items[0]->page);
    }

    public function testResolveWithDefaultValues(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PaginationParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(PaginationParams::class, $items[0]);
        self::assertSame(1, $items[0]->page);
        self::assertSame(20, $items[0]->perPage);
    }
}
