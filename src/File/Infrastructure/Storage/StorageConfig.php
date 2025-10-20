<?php

declare(strict_types=1);

namespace App\File\Infrastructure\Storage;

use InvalidArgumentException;

/**
 * Value Object representing parsed storage configuration from DSN.
 *
 * Examples:
 * - storage://local?root=/var/storage
 * - storage://s3?bucket=my-bucket&region=eu-west-1
 * - storage://ftp?host=ftp.example.com&username=user
 */
final readonly class StorageConfig
{
    /**
     * @param string               $driver  Storage driver name (e.g., "local", "s3", "ftp")
     * @param array<string,string> $options Driver-specific options from query string
     */
    public function __construct(
        public string $driver,
        public array $options = [],
    ) {
        if (empty($this->driver)) {
            throw new InvalidArgumentException('Storage driver cannot be empty');
        }
    }

    /**
     * Get option value with optional default.
     *
     * @param string      $key     Option key
     * @param null|string $default Default value if key not found
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Check if option exists.
     *
     * @param string $key Option key
     */
    public function has(string $key): bool
    {
        return isset($this->options[$key]);
    }
}
