<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\Request;

use App\Photo\UserInterface\Http\Request\BulkDeletePhotosRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class BulkDeletePhotosRequestTest extends TestCase
{
    public function testFromRequestWithValidData(): void
    {
        $photoIds = [
            '550e8400-e29b-41d4-a716-446655440001',
            '550e8400-e29b-41d4-a716-446655440002',
        ];

        $request = $this->createRequest(['photoIds' => $photoIds]);
        $dto = BulkDeletePhotosRequest::fromRequest($request);

        self::assertSame($photoIds, $dto->photoIds);
    }

    public function testFromRequestThrowsOnEmptyBody(): void
    {
        $request = Request::create('/api/photos/bulk-delete', 'POST', [], [], [], [], '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request body cannot be empty');

        BulkDeletePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnInvalidJson(): void
    {
        $request = Request::create('/api/photos/bulk-delete', 'POST', [], [], [], [], 'not json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        BulkDeletePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnMissingPhotoIds(): void
    {
        $request = $this->createRequest(['other' => 'data']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "photoIds" must be an array');

        BulkDeletePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnEmptyPhotoIds(): void
    {
        $request = $this->createRequest(['photoIds' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "photoIds" cannot be empty');

        BulkDeletePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnTooManyPhotoIds(): void
    {
        $photoIds = [];
        for ($i = 0; $i < 101; ++$i) {
            $photoIds[] = sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i);
        }

        $request = $this->createRequest(['photoIds' => $photoIds]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot process more than 100 photos at once');

        BulkDeletePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnInvalidPhotoIdFormat(): void
    {
        $request = $this->createRequest(['photoIds' => ['not-a-uuid']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid photo ID format');

        BulkDeletePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnNonStringPhotoId(): void
    {
        $request = $this->createRequest(['photoIds' => [123]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a string');

        BulkDeletePhotosRequest::fromRequest($request);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRequest(array $data): Request
    {
        return Request::create(
            '/api/photos/bulk-delete',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }
}
