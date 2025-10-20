<?php

declare(strict_types=1);

namespace App\File\Infrastructure\Storage\Bridge;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Infrastructure\Storage\StorageConfig;
use InvalidArgumentException;
use RuntimeException;

/**
 * Interface for storage adapter bridges.
 *
 * Bridges are separate packages that provide FileStorageInterface implementations
 * for external storage backends (S3, FTP, Google Cloud Storage, etc.).
 *
 * Bridges are auto-discovered via Symfony tagged services (tag: storage.bridge).
 *
 * Example bridge packages:
 * - yggdrasilcloud/storage-s3
 * - yggdrasilcloud/storage-ftp
 * - yggdrasilcloud/storage-gcs
 */
interface StorageBridgeInterface
{
    /**
     * Check if this bridge supports the given storage driver.
     *
     * @param string $driver Storage driver name (e.g., "s3", "ftp", "gcs")
     *
     * @return bool True if this bridge can handle the driver
     */
    public function supports(string $driver): bool;

    /**
     * Create a FileStorageInterface instance for the given configuration.
     *
     * @param StorageConfig $config Parsed storage configuration
     *
     * @return FileStorageInterface Storage adapter instance
     *
     * @throws InvalidArgumentException If configuration is invalid
     * @throws RuntimeException         If adapter cannot be created
     */
    public function create(StorageConfig $config): FileStorageInterface;
}
