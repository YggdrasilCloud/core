<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\UserInterface\Http\Responder;

use App\File\Domain\Port\FileStorageInterface;
use App\Photo\Application\Query\GetPhotoFile\FileResponseModel;
use App\Photo\UserInterface\Http\Responder\FileResponder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class FileResponderTest extends TestCase
{
    public function testRespondReturnsStreamedResponse(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/test-photo.jpg',
            mimeType: 'image/jpeg',
            cacheMaxAge: 3600
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        self::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testRespondSetsCorrectContentType(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/test.png',
            mimeType: 'image/png',
            cacheMaxAge: 7200
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        self::assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function testRespondSetsPublicCacheControl(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/test.jpg',
            mimeType: 'image/jpeg',
            cacheMaxAge: 3600
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        self::assertTrue($response->headers->getCacheControlDirective('public'));
    }

    public function testRespondSetsMaxAgeCacheDirective(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/test.jpg',
            mimeType: 'image/jpeg',
            cacheMaxAge: 7200
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        self::assertSame('7200', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testRespondSetsSharedMaxAgeCacheDirective(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/test.jpg',
            mimeType: 'image/jpeg',
            cacheMaxAge: 1800
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        self::assertSame('1800', $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function testRespondCallsFileStorageReadStreamWithCorrectKey(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);

        // Create a temporary file to return as stream
        $tempFile = tmpfile();
        self::assertNotFalse($tempFile, 'Failed to create temporary file');
        fwrite($tempFile, 'test content');
        rewind($tempFile);

        $fileStorage->expects(self::once())
            ->method('readStream')
            ->with('photos/folder-id/photo-id.jpg')
            ->willReturn($tempFile)
        ;

        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/folder-id/photo-id.jpg',
            mimeType: 'image/jpeg',
            cacheMaxAge: 3600
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        // Execute the callback to trigger the mock expectation
        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        self::assertSame('test content', $output);
    }

    public function testRespondStreamsFileContentCorrectly(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);

        // Create a temporary file with test content
        $tempFile = tmpfile();
        self::assertNotFalse($tempFile, 'Failed to create temporary file');
        $testContent = 'This is test photo content';
        fwrite($tempFile, $testContent);
        rewind($tempFile);

        $fileStorage->method('readStream')
            ->willReturn($tempFile)
        ;

        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/test.jpg',
            mimeType: 'image/jpeg',
            cacheMaxAge: 3600
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        // Capture output from streaming
        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        self::assertSame($testContent, $output);
    }

    public function testRespondCallbackDoesNotThrowExceptionWhenStreaming(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);

        // Create a temporary file
        $tempFile = tmpfile();
        self::assertNotFalse($tempFile, 'Failed to create temporary file');
        fwrite($tempFile, 'test content for streaming');
        rewind($tempFile);

        $fileStorage->method('readStream')
            ->willReturn($tempFile)
        ;

        $responder = new FileResponder($fileStorage);

        $model = new FileResponseModel(
            storageKey: 'photos/test.jpg',
            mimeType: 'image/jpeg',
            cacheMaxAge: 3600
        );
        $request = new Request();

        $response = $responder->respond($model, $request);

        // Execute callback and verify no exception is thrown
        ob_start();

        try {
            $response->sendContent();
            $success = true;
        } catch (Throwable $e) {
            $success = false;
        } finally {
            ob_end_clean();
        }

        self::assertTrue($success, 'Callback should execute without throwing exceptions');
    }

    public function testRespondHandlesDifferentMimeTypes(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $responder = new FileResponder($fileStorage);

        $mimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'application/pdf',
        ];

        foreach ($mimeTypes as $mimeType) {
            $model = new FileResponseModel(
                storageKey: 'files/test',
                mimeType: $mimeType,
                cacheMaxAge: 3600
            );
            $request = new Request();

            $response = $responder->respond($model, $request);

            self::assertSame($mimeType, $response->headers->get('Content-Type'));
        }
    }

    public function testRespondHandlesDifferentCacheMaxAgeValues(): void
    {
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $responder = new FileResponder($fileStorage);

        $cacheValues = [
            0,      // No cache
            3600,   // 1 hour
            86400,  // 1 day
            604800, // 1 week
        ];

        foreach ($cacheValues as $cacheMaxAge) {
            $model = new FileResponseModel(
                storageKey: 'files/test',
                mimeType: 'image/jpeg',
                cacheMaxAge: $cacheMaxAge
            );
            $request = new Request();

            $response = $responder->respond($model, $request);

            self::assertSame((string) $cacheMaxAge, $response->headers->getCacheControlDirective('max-age'));
            self::assertSame((string) $cacheMaxAge, $response->headers->getCacheControlDirective('s-maxage'));
        }
    }
}
