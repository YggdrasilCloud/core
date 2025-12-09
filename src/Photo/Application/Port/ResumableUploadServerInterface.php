<?php

declare(strict_types=1);

namespace App\Photo\Application\Port;

use Symfony\Component\HttpFoundation\Response;

/**
 * Port for resumable upload server implementations.
 *
 * This interface abstracts the resumable upload protocol (e.g., Tus)
 * from the UserInterface layer, allowing the controller to depend
 * on this application port rather than infrastructure implementations.
 */
interface ResumableUploadServerInterface
{
    /**
     * Handle a resumable upload request and return the response.
     *
     * The implementation is responsible for:
     * - Processing the upload protocol (e.g., Tus)
     * - Managing upload state and chunks
     * - Triggering upload completion events
     */
    public function serve(): Response;
}
