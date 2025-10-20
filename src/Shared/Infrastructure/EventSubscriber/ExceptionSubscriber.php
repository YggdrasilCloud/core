<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use App\Photo\Domain\Exception\FolderNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
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
            $response = new JsonResponse(
                [
                    'type' => 'https://tools.ietf.org/html/rfc7231#section-6.5.4',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => $folderNotFoundException->getMessage(),
                ],
                404
            );

            $event->setResponse($response);
        }
    }

    private function findFolderNotFoundException(\Throwable $exception): ?FolderNotFoundException
    {
        if ($exception instanceof FolderNotFoundException) {
            return $exception;
        }

        // Check previous exception (for wrapped exceptions like HandlerFailedException)
        if ($exception->getPrevious() !== null) {
            return $this->findFolderNotFoundException($exception->getPrevious());
        }

        return null;
    }
}
