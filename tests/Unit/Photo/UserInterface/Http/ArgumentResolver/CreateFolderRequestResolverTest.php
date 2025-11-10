<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\ArgumentResolver;

use App\Photo\UserInterface\Http\ArgumentResolver\CreateFolderRequestResolver;
use App\Photo\UserInterface\Http\Request\CreateFolderRequest;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateFolderRequestResolverTest extends TestCase
{
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private CreateFolderRequestResolver $resolver;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->resolver = new CreateFolderRequestResolver($this->serializer, $this->validator);
    }

    public function testResolveReturnsEmptyArrayWhenTypeDoesNotMatch(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('SomeOtherType');

        $result = $this->resolver->resolve($request, $argument);

        self::assertSame([], iterator_to_array($result));
    }

    public function testResolveYieldsCreateFolderRequestWhenTypeMatches(): void
    {
        $jsonContent = json_encode([
            'name' => 'Vacation Photos',
            'ownerId' => 'user-123',
        ], JSON_THROW_ON_ERROR);

        $request = new Request([], [], [], [], [], [], $jsonContent);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(CreateFolderRequest::class);

        $validatorMock = $this->validator;
        assert($validatorMock instanceof MockObject);
        $validatorMock
            ->method('validate')
            ->willReturn(new ConstraintViolationList())
        ;

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(CreateFolderRequest::class, $items[0]);
        self::assertSame('Vacation Photos', $items[0]->name);
        self::assertSame('user-123', $items[0]->ownerId);
        self::assertNull($items[0]->parentId);
    }

    public function testResolveYieldsCreateFolderRequestWithParentId(): void
    {
        $jsonContent = json_encode([
            'name' => 'Subfolder',
            'ownerId' => 'user-123',
            'parentId' => 'parent-folder-id',
        ], JSON_THROW_ON_ERROR);

        $request = new Request([], [], [], [], [], [], $jsonContent);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(CreateFolderRequest::class);

        $validatorMock = $this->validator;
        assert($validatorMock instanceof MockObject);
        $validatorMock
            ->method('validate')
            ->willReturn(new ConstraintViolationList())
        ;

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        self::assertCount(1, $items);
        self::assertInstanceOf(CreateFolderRequest::class, $items[0]);
        self::assertSame('Subfolder', $items[0]->name);
        self::assertSame('user-123', $items[0]->ownerId);
        self::assertSame('parent-folder-id', $items[0]->parentId);
    }

    public function testResolvePropagatesExceptionWhenRequestBodyEmpty(): void
    {
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(CreateFolderRequest::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request body cannot be empty');

        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    public function testResolvePropagatesExceptionWhenJsonInvalid(): void
    {
        $request = new Request([], [], [], [], [], [], 'invalid json');
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(CreateFolderRequest::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    public function testResolvePropagatesExceptionWhenNameMissing(): void
    {
        $jsonContent = json_encode([
            'ownerId' => 'user-123',
        ], JSON_THROW_ON_ERROR);

        $request = new Request([], [], [], [], [], [], $jsonContent);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(CreateFolderRequest::class);

        $validatorMock = $this->validator;
        assert($validatorMock instanceof MockObject);
        $validatorMock
            ->method('validate')
            ->willReturn(new ConstraintViolationList())
        ;

        $result = $this->resolver->resolve($request, $argument);
        $items = iterator_to_array($result);

        // Should still create the object but with empty name
        self::assertCount(1, $items);
        self::assertSame('', $items[0]->name);
    }
}
