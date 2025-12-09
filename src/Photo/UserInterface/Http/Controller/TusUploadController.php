<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Infrastructure\Tus\TusServerFactory;
use App\Photo\Infrastructure\Tus\TusUploadCompleteSubscriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TusPhp\Events\UploadComplete;

/**
 * Controller handling Tus protocol requests for resumable uploads.
 *
 * Tus is a protocol for resumable file uploads. This controller handles:
 * - OPTIONS: Returns Tus server capabilities
 * - POST: Creates a new upload
 * - HEAD: Returns upload offset
 * - PATCH: Receives upload chunks
 * - DELETE: Cancels an upload
 *
 * @see https://tus.io/protocols/resumable-upload.html
 */
final readonly class TusUploadController
{
    public function __construct(
        private TusServerFactory $tusServerFactory,
        private TusUploadCompleteSubscriber $uploadCompleteSubscriber,
    ) {}

    /**
     * Main Tus endpoint - handles all HTTP methods.
     */
    #[Route('/api/uploads/tus', name: 'tus_upload_main', methods: ['OPTIONS', 'POST'])]
    #[Route('/api/uploads/tus/{uploadKey}', name: 'tus_upload_file', methods: ['HEAD', 'PATCH', 'DELETE'])]
    public function __invoke(): Response
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
