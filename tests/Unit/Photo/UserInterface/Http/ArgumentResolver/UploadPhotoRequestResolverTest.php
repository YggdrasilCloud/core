<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\ArgumentResolver\UploadPhotoRequestResolver;
use App\Photo\UserInterface\Http\Request\UploadPhotoRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class UploadPhotoRequestResolverTest extends TestCase
{
    private UploadPhotoRequestResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new UploadPhotoRequestResolver();
    }

    public function testResolveReturnsEmptyArrayWhenTypeDoesNotMatch(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('SomeOtherType');

        $result = $this->resolver->resolve($request, $argument);

        self::assertSame([], iterator_to_array($result));
    }

    public function testResolveYieldsUploadPhotoRequestWhenTypeMatches(): void
    {
        $uploadedFile = $this->createMock(UploadedFile::class);

        $request = new Request(
            [],
            ['ownerId' => 'user-123'],
            [],
            [],
            ['photo' => $uploadedFile]
        );

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(UploadPhotoRequest::class);

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(UploadPhotoRequest::class, $items[0]);
        self::assertSame($uploadedFile, $items[0]->file);
        self::assertSame('user-123', $items[0]->ownerId);
    }

    public function testResolvePropagatesExceptionWhenFileMissing(): void
    {
        $request = new Request(
            [],
            ['ownerId' => 'user-123']
        );

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(UploadPhotoRequest::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required file: photo');

        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    public function testResolvePropagatesExceptionWhenOwnerIdMissing(): void
    {
        $uploadedFile = $this->createMock(UploadedFile::class);

        $request = new Request(
            [],
            [],
            [],
            [],
            ['photo' => $uploadedFile]
        );

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(UploadPhotoRequest::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: ownerId');

        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    public function testResolvePropagatesExceptionWhenOwnerIdEmpty(): void
    {
        $uploadedFile = $this->createMock(UploadedFile::class);

        $request = new Request(
            [],
            ['ownerId' => ''],
            [],
            [],
            ['photo' => $uploadedFile]
        );

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(UploadPhotoRequest::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: ownerId');

        iterator_to_array($this->resolver->resolve($request, $argument));
    }
}
