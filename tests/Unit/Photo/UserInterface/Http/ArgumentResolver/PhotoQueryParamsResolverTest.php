<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\ArgumentResolver\PhotoQueryParamsResolver;
use App\Photo\UserInterface\Http\Request\PhotoQueryParams;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 *
 * @coversNothing
 */
final class PhotoQueryParamsResolverTest extends TestCase
{
    private PhotoQueryParamsResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PhotoQueryParamsResolver();
    }

    public function testResolveReturnsEmptyArrayWhenTypeDoesNotMatch(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('SomeOtherType');

        $result = $this->resolver->resolve($request, $argument);

        self::assertSame([], iterator_to_array($result));
    }

    public function testResolveYieldsPhotoQueryParamsWhenTypeMatches(): void
    {
        $request = new Request([
            'search' => 'birthday',
            'sortBy' => 'fileName',
            'sortOrder' => 'asc',
        ]);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PhotoQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(PhotoQueryParams::class, $items[0]);
        self::assertSame('birthday', $items[0]->search);
        self::assertSame('fileName', $items[0]->sortBy);
        self::assertSame('asc', $items[0]->sortOrder);
    }

    public function testResolvePropagatesExceptionFromPhotoQueryParams(): void
    {
        $request = new Request(['sortBy' => 'invalid_field']);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PhotoQueryParams::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sortBy');

        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    public function testResolveWithDefaultValues(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PhotoQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(PhotoQueryParams::class, $items[0]);
        self::assertNull($items[0]->search);
        self::assertSame('uploadedAt', $items[0]->sortBy);
        self::assertSame('desc', $items[0]->sortOrder);
    }

    public function testResolveWithMimeTypes(): void
    {
        $request = new Request([
            'mimeType' => 'image/jpeg,image/png',
        ]);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PhotoQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(PhotoQueryParams::class, $items[0]);
        self::assertSame(['image/jpeg', 'image/png'], $items[0]->mimeTypes);
    }

    public function testResolveWithSizeRange(): void
    {
        $request = new Request([
            'sizeMin' => '1024',
            'sizeMax' => '10485760',
        ]);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PhotoQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(PhotoQueryParams::class, $items[0]);
        self::assertSame(1024, $items[0]->sizeMin);
        self::assertSame(10485760, $items[0]->sizeMax);
    }

    public function testResolveWithExtensions(): void
    {
        $request = new Request([
            'extension' => 'jpg,png',
        ]);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(PhotoQueryParams::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(PhotoQueryParams::class, $items[0]);
        self::assertSame(['jpg', 'png'], $items[0]->extensions);
    }
}
