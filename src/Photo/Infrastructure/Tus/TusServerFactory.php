<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Tus;

use TusPhp\Cache\FileStore;
use TusPhp\Tus\Server;

/**
 * Factory to create and configure the Tus server instance.
 *
 * Tus is a protocol for resumable file uploads. This factory configures
 * the server with appropriate settings for large file uploads.
 */
final readonly class TusServerFactory
{
    public function __construct(
        private string $uploadDir,
        private string $cacheDir,
        private int $maxUploadSize,
    ) {}

    public function create(): Server
    {
        // Create cache with custom directory
        $cache = new FileStore($this->cacheDir);

        $server = new Server($cache);

        // Configure upload directory for chunks
        $server->setUploadDir($this->uploadDir);

        // Configure max upload size (0 = unlimited)
        $server->setMaxUploadSize($this->maxUploadSize);

        // Set API path to match our route
        $server->setApiPath('/api/uploads/tus');

        return $server;
    }
}
