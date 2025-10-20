<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Shared\UserInterface\Http\Responder\JsonResponder;
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

        // Check if it's directly a FolderNotFoundException
        // or if it's wrapped in another exception (like Messenger's HandlerFailedException)
        $folderNotFoundException = $this->findFolderNotFoundException($exception);

        if ($folderNotFoundException !== null) {
            $response = $this->responder->notFound(
                'Not Found',
                $folderNotFoundException->getMessage()
            );

            $event->setResponse($response);
        }
    }

    private function findFolderNotFoundException(Throwable $exception): ?FolderNotFoundException
    {
        if ($exception instanceof FolderNotFoundException) {
            return $exception;
        }

        // Handle Symfony Messenger's HandlerFailedException which stores wrapped exceptions
        if ($exception instanceof HandlerFailedException) {
            foreach ($exception->getWrappedExceptions() as $wrappedException) {
                $found = $this->findFolderNotFoundException($wrappedException);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        // Check previous exception (for other wrapped exceptions)
        if ($exception->getPrevious() !== null) {
            return $this->findFolderNotFoundException($exception->getPrevious());
        }

        return null;
    }
}
