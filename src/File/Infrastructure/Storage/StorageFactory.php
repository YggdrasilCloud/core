<?php

declare(strict_types=1);

namespace App\File\Infrastructure\Storage;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Infrastructure\Storage\Adapter\LocalStorage;
use App\File\Infrastructure\Storage\Bridge\StorageBridgeInterface;
use InvalidArgumentException;
use LogicException;

/**
 * Factory for creating FileStorageInterface instances from DSN strings.
 *
 * Built-in support:
 * - local: Native PHP filesystem (no dependencies)
 *
 * External support via bridges:
 * - s3: AWS S3 / MinIO (via yggdrasilcloud/storage-s3)
 * - ftp: FTP/FTPS (via yggdrasilcloud/storage-ftp)
 * - gcs: Google Cloud Storage (via yggdrasilcloud/storage-gcs)
 * - etc.
 *
 * Bridges are auto-discovered via Symfony tagged services (tag: storage.bridge).
 */
final readonly class StorageFactory
{
    /**
     * @param StorageDsnParser                 $parser  DSN parser
     * @param iterable<StorageBridgeInterface> $bridges Registered storage bridges (tagged: storage.bridge)
     */
    public function __construct(
        private StorageDsnParser $parser,
        private iterable $bridges = [],
        private ?string $projectDir = null,
    ) {}

    /**
     * Create a FileStorageInterface from DSN string.
     *
     * @param string $dsn Storage DSN (e.g., "storage://local?root=/var/storage")
     *
     * @return FileStorageInterface Storage adapter instance
     *
     * @throws InvalidArgumentException If DSN format is invalid
     * @throws LogicException           If no adapter found for driver
     */
    public function create(string $dsn): FileStorageInterface
    {
        $config = $this->parser->parse($dsn);

        // Built-in: local (core only, no dependencies)
        if ($config->driver === 'local') {
            /** @var string $basePath */
            $basePath = $config->get('root') ?? '/var/storage';

            // Resolve relative paths relative to project directory
            if ($this->projectDir !== null && !str_starts_with($basePath, '/')) {
                $basePath = $this->projectDir.'/'.$basePath;
            }

            /** @var int $maxKeyLength */
            $maxKeyLength = $config->getInt('max_key_length') ?? 1024;

            /** @var int $maxComponentLength */
            $maxComponentLength = $config->getInt('max_component_length') ?? 255;

            return new LocalStorage($basePath, $maxKeyLength, $maxComponentLength);
        }

        // Try registered bridges
        foreach ($this->bridges as $bridge) {
            if ($bridge->supports($config->driver)) {
                return $bridge->create($config);
            }
        }

        // No adapter found
        throw new LogicException(sprintf(
            'No storage adapter found for driver "%s". '
            .'To use this driver, install the corresponding bridge package: '
            .'composer require yggdrasilcloud/storage-%s. '
            .'See https://github.com/YggdrasilCloud/core#storage-bridges for available bridges.',
            $config->driver,
            $config->driver
        ));
    }
}
