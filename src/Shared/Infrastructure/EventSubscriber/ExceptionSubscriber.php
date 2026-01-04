<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use App\Photo\Domain\Exception\FolderNotEmptyException;
use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private JsonResponder $responder,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Check if it's a FolderNotEmptyException
        $folderNotEmptyException = $this->findException($exception, FolderNotEmptyException::class);
        if ($folderNotEmptyException !== null) {
            $response = $this->responder->badRequest(
                'Folder Not Empty',
                $folderNotEmptyException->getMessage()
            );
            $event->setResponse($response);

            return;
        }

        // Check if it's a FolderNotFoundException
        $folderNotFoundException = $this->findException($exception, FolderNotFoundException::class);
        if ($folderNotFoundException !== null) {
            $response = $this->responder->notFound(
                'Not Found',
                $folderNotFoundException->getMessage()
            );
            $event->setResponse($response);

            return;
        }

        // Check if it's an InvalidArgumentException (validation errors like cycle detection)
        $invalidArgumentException = $this->findException($exception, InvalidArgumentException::class);
        if ($invalidArgumentException !== null) {
            $response = $this->responder->badRequest(
                'Invalid Request',
                $invalidArgumentException->getMessage()
            );
            $event->setResponse($response);
        }
    }

    /**
     * @template T of Throwable
     *
     * @param class-string<T> $exceptionClass
     *
     * @return null|T
     */
    private function findException(Throwable $exception, string $exceptionClass): ?Throwable
    {
        if ($exception instanceof $exceptionClass) {
            return $exception;
        }

        // Handle Symfony Messenger's HandlerFailedException which stores wrapped exceptions
        if ($exception instanceof HandlerFailedException) {
            foreach ($exception->getWrappedExceptions() as $wrapped) {
                $found = $this->findException($wrapped, $exceptionClass);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        // Check previous exception (for other wrapped exceptions)
        if ($exception->getPrevious() !== null) {
            return $this->findException($exception->getPrevious(), $exceptionClass);
        }

        return null;
    }
}
