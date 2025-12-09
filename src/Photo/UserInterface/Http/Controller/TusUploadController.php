<?php

declare(strict_types=1);

namespace App\Photo\UserInterface\Http\Controller;

use App\Photo\Application\Port\ResumableUploadServerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        private ResumableUploadServerInterface $uploadServer,
    ) {}

    /**
     * Main Tus endpoint - handles all HTTP methods.
     */
    #[Route('/api/uploads/tus', name: 'tus_upload_main', methods: ['OPTIONS', 'POST'])]
    #[Route('/api/uploads/tus/{uploadKey}', name: 'tus_upload_file', methods: ['HEAD', 'PATCH', 'DELETE'])]
    public function __invoke(): Response
    {
        return $this->uploadServer->serve();
    }
}
