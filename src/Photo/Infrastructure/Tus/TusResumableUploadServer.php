<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Tus;

use App\Photo\Application\Port\ResumableUploadServerInterface;
use Symfony\Component\HttpFoundation\Response;
use TusPhp\Events\UploadComplete;

/**
 * Tus protocol implementation of the resumable upload server.
 *
 * This class combines the Tus server factory with the upload complete
 * subscriber to provide a complete resumable upload solution.
 */
final readonly class TusResumableUploadServer implements ResumableUploadServerInterface
{
    public function __construct(
        private TusServerFactory $tusServerFactory,
        private TusUploadCompleteSubscriber $uploadCompleteSubscriber,
    ) {}

    public function serve(): Response
    {
        $server = $this->tusServerFactory->create();

        // Register event listener for upload completion
        $server->event()->addListener(
            UploadComplete::NAME,
            $this->uploadCompleteSubscriber
        );

        // tus-php handles the request internally and returns a Symfony Response
        return $server->serve();
    }
}
