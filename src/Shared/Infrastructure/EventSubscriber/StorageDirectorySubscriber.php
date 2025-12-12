<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Creates required storage directories on first request.
 *
 * This ensures the application works immediately after a fresh clone
 * without manual directory creation.
 */
final class StorageDirectorySubscriber implements EventSubscriberInterface
{
    private bool $directoriesChecked = false;

    public function __construct(
        private readonly string $projectDir,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->directoriesChecked) {
            return;
        }

        $this->directoriesChecked = true;

        $directories = [
            $this->projectDir.'/var/storage/photos',
            $this->projectDir.'/var/storage/thumbs',
            $this->projectDir.'/var/storage/uploads/tus',
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }
}
