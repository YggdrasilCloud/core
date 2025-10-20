<?php

declare(strict_types=1);

namespace App\File\Infrastructure\Storage;

use InvalidArgumentException;

/**
 * Parses storage DSN strings into StorageConfig objects.
 *
 * DSN Format: storage://driver?option1=value1&option2=value2
 *
 * Examples:
 * - storage://local?root=/var/storage
 * - storage://s3?bucket=my-bucket&region=eu-west-1
 * - storage://ftp?host=ftp.example.com&username=user&password=pass
 */
final readonly class StorageDsnParser
{
    /**
     * Parse DSN string into StorageConfig.
     *
     * @param string $dsn Storage DSN (e.g., "storage://local?root=/var/storage")
     *
     * @return StorageConfig Parsed configuration
     *
     * @throws InvalidArgumentException If DSN format is invalid
     */
    public function parse(string $dsn): StorageConfig
    {
        if (empty($dsn)) {
            throw new InvalidArgumentException('Storage DSN cannot be empty');
        }

        $parsed = parse_url($dsn);

        if ($parsed === false) {
            throw new InvalidArgumentException(sprintf('Invalid DSN format: "%s"', $dsn));
        }

        // Validate scheme
        if (!isset($parsed['scheme']) || $parsed['scheme'] !== 'storage') {
            throw new InvalidArgumentException(sprintf(
                'DSN scheme must be "storage", got "%s" in: %s',
                $parsed['scheme'] ?? 'none',
                $dsn
            ));
        }

        // Extract driver from host
        if (!isset($parsed['host']) || empty($parsed['host'])) {
            throw new InvalidArgumentException(sprintf(
                'DSN must specify a driver as host (e.g., storage://local), got: %s',
                $dsn
            ));
        }

        $driver = $parsed['host'];

        // Parse query string options
        $options = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $options);
        }

        // Ensure all values are strings
        $stringOptions = [];
        foreach ($options as $key => $value) {
            $stringOptions[(string) $key] = is_array($value) ? '' : (string) $value;
        }

        return new StorageConfig(
            driver: $driver,
            options: $stringOptions,
        );
    }
}
