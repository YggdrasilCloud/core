<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\EventListener;

use InvalidArgumentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final readonly class ValidationExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Convert InvalidArgumentException from ArgumentResolvers to 400 Bad Request
        if ($exception instanceof InvalidArgumentException) {
            $response = new JsonResponse(
                [
                    'type' => 'about:blank',
                    'title' => $exception->getMessage(),
                    'status' => Response::HTTP_BAD_REQUEST,
                ],
                Response::HTTP_BAD_REQUEST,
                ['Content-Type' => 'application/problem+json']
            );

            $event->setResponse($response);
        }
    }
}
