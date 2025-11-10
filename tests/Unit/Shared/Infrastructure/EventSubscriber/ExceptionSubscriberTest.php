<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\EventSubscriber;

use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Shared\Infrastructure\EventSubscriber\ExceptionSubscriber;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
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
}
