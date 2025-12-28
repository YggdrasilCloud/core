<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\Request;

use App\Photo\UserInterface\Http\Request\BulkMovePhotosRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class BulkMovePhotosRequestTest extends TestCase
{
    public function testFromRequestWithValidData(): void
    {
        $photoIds = [
            '550e8400-e29b-41d4-a716-446655440001',
            '550e8400-e29b-41d4-a716-446655440002',
        ];
        $targetFolderId = '550e8400-e29b-41d4-a716-446655440010';

        $request = $this->createRequest([
            'photoIds' => $photoIds,
            'targetFolderId' => $targetFolderId,
        ]);

        $dto = BulkMovePhotosRequest::fromRequest($request);

        self::assertSame($photoIds, $dto->photoIds);
        self::assertSame($targetFolderId, $dto->targetFolderId);
    }

    public function testFromRequestThrowsOnEmptyBody(): void
    {
        $request = Request::create('/api/photos/bulk-move', 'PATCH', [], [], [], [], '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request body cannot be empty');

        BulkMovePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnMissingTargetFolderId(): void
    {
        $request = $this->createRequest([
            'photoIds' => ['550e8400-e29b-41d4-a716-446655440001'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "targetFolderId" is required');

        BulkMovePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnEmptyTargetFolderId(): void
    {
        $request = $this->createRequest([
            'photoIds' => ['550e8400-e29b-41d4-a716-446655440001'],
            'targetFolderId' => '',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "targetFolderId" is required');

        BulkMovePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnInvalidTargetFolderIdFormat(): void
    {
        $request = $this->createRequest([
            'photoIds' => ['550e8400-e29b-41d4-a716-446655440001'],
            'targetFolderId' => 'not-a-uuid',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid targetFolderId format');

        BulkMovePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnEmptyPhotoIds(): void
    {
        $request = $this->createRequest([
            'photoIds' => [],
            'targetFolderId' => '550e8400-e29b-41d4-a716-446655440010',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "photoIds" cannot be empty');

        BulkMovePhotosRequest::fromRequest($request);
    }

    public function testFromRequestThrowsOnTooManyPhotoIds(): void
    {
        $photoIds = [];
        for ($i = 0; $i < 101; ++$i) {
            $photoIds[] = sprintf('550e8400-e29b-41d4-a716-4466554400%02d', $i % 100);
        }

        $request = $this->createRequest([
            'photoIds' => $photoIds,
            'targetFolderId' => '550e8400-e29b-41d4-a716-446655440010',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot process more than 100 photos at once');

        BulkMovePhotosRequest::fromRequest($request);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRequest(array $data): Request
    {
        return Request::create(
            '/api/photos/bulk-move',
            'PATCH',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }
}
