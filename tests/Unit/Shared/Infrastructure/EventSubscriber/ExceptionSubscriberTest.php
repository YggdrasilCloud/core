<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\EventSubscriber;

use App\Photo\Domain\Exception\FolderNotEmptyException;
use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Photo\Domain\Model\FolderId;
use App\Shared\Infrastructure\EventSubscriber\ExceptionSubscriber;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class ExceptionSubscriberTest extends TestCase
{
    public function testHandlesFolderNotFoundExceptionDirectly(): void
    {
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new FolderNotFoundException('Folder not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(404, $event->getResponse()->getStatusCode());
    }

    public function testHandlesFolderNotFoundExceptionWrappedInHandlerFailedException(): void
    {
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $folderNotFoundException = new FolderNotFoundException('Wrapped folder not found');
        $envelope = new Envelope(new stdClass());
        $wrappedException = new HandlerFailedException($envelope, [$folderNotFoundException]);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrappedException);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());

        // Verify the response body contains the correct error details
        $responseContent = $response->getContent();
        self::assertIsString($responseContent);
        $content = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertSame('about:blank', $content['type']);
        self::assertSame('Not Found', $content['title']);
        self::assertSame('Wrapped folder not found', $content['detail']);
    }

    public function testIgnoresOtherExceptions(): void
    {
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new RuntimeException('Some other error');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    public function testHandlesFolderNotEmptyExceptionDirectly(): void
    {
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $folderId = FolderId::generate();
        $exception = FolderNotEmptyException::withContent($folderId, 5, 2);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $responseContent = $response->getContent();
        self::assertIsString($responseContent);
        $content = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertSame('Folder Not Empty', $content['title']);
    }

    public function testHandlesFolderNotEmptyExceptionWrappedInHandlerFailedException(): void
    {
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $folderId = FolderId::generate();
        $folderNotEmptyException = FolderNotEmptyException::withContent($folderId, 3, 1);
        $envelope = new Envelope(new stdClass());
        $wrappedException = new HandlerFailedException($envelope, [$folderNotEmptyException]);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrappedException);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $responseContent = $response->getContent();
        self::assertIsString($responseContent);
        $content = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertSame('Folder Not Empty', $content['title']);
    }

    public function testHandlesInvalidArgumentExceptionDirectly(): void
    {
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new InvalidArgumentException('Cannot set parent: would create a cycle');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $responseContent = $response->getContent();
        self::assertIsString($responseContent);
        $content = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertSame('Invalid Request', $content['title']);
        self::assertSame('Cannot set parent: would create a cycle', $content['detail']);
    }

    public function testHandlesInvalidArgumentExceptionWrappedInHandlerFailedException(): void
    {
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $invalidArgumentException = new InvalidArgumentException('A folder cannot be its own parent');
        $envelope = new Envelope(new stdClass());
        $wrappedException = new HandlerFailedException($envelope, [$invalidArgumentException]);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrappedException);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());

        $responseContent = $response->getContent();
        self::assertIsString($responseContent);
        $content = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertSame('Invalid Request', $content['title']);
        self::assertSame('A folder cannot be its own parent', $content['detail']);
    }

    public function testFolderNotEmptyHasPriorityOverFolderNotFound(): void
    {
        // FolderNotEmptyException should be handled even if there's also a FolderNotFoundException in the chain
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $folderId = FolderId::generate();
        $exception = FolderNotEmptyException::withContent($folderId, 1, 0);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        // Should be 400 (FolderNotEmpty) not 404 (FolderNotFound)
        self::assertSame(400, $response->getStatusCode());
    }

    public function testFolderNotFoundHasPriorityOverInvalidArgument(): void
    {
        // When we have a FolderNotFoundException, it should return 404 not 400
        $responder = new JsonResponder();
        $subscriber = new ExceptionSubscriber($responder);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $exception = new FolderNotFoundException('Folder not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        // Should be 404 not 400
        self::assertSame(404, $response->getStatusCode());
    }
}
