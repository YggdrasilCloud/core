<?php

declare(strict_types=1);

namespace App\File\Infrastructure\Storage\Adapter;

use App\File\Domain\Model\StoredObject;
use App\File\Domain\Port\FileStorageInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Local filesystem storage adapter.
 *
 * Uses native PHP filesystem functions (fopen, stream_copy_to_stream, etc.).
 * No external dependencies - pure PHP implementation.
 *
 * DSN Example: storage://local?root=/var/storage
 */
final readonly class LocalStorage implements FileStorageInterface
{
    private const DEFAULT_MAX_KEY_LENGTH = 1024;
    private const DEFAULT_MAX_COMPONENT_LENGTH = 255;

    /**
     * @param string $basePath           Base directory for file storage (e.g., "/var/storage")
     * @param int    $maxKeyLength       Maximum total key length (default: 1024 chars)
     * @param int    $maxComponentLength Maximum path component length (default: 255 chars, filesystem limit)
     */
    public function __construct(
        private string $basePath,
        private int $maxKeyLength = self::DEFAULT_MAX_KEY_LENGTH,
        private int $maxComponentLength = self::DEFAULT_MAX_COMPONENT_LENGTH,
    ) {
        if (empty($this->basePath)) {
            throw new InvalidArgumentException('Base path cannot be empty');
        }

        if ($this->maxKeyLength <= 0) {
            throw new InvalidArgumentException('Max key length must be positive');
        }

        if ($this->maxComponentLength <= 0) {
            throw new InvalidArgumentException('Max component length must be positive');
        }
    }

    public function save($stream, string $key, string $mimeType, int $sizeInBytes): StoredObject
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a valid resource');
        }

        $fullPath = $this->getFullPath($key);
        $directory = dirname($fullPath);

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
            }
        }

        // Open destination file
        $destination = fopen($fullPath, 'w');
        if ($destination === false) {
            throw new RuntimeException(sprintf('Failed to open file for writing: %s', $fullPath));
        }

        try {
            // Copy stream to file
            $bytesWritten = stream_copy_to_stream($stream, $destination);

            if ($bytesWritten === false) {
                throw new RuntimeException(sprintf('Failed to write file: %s', $fullPath));
            }

            // Verify size if provided (sizeInBytes > 0)
            // -1 or 0 means unknown size, skip verification
            if ($sizeInBytes > 0 && $bytesWritten !== $sizeInBytes) {
                throw new RuntimeException(sprintf(
                    'File size mismatch: expected %d bytes, wrote %d bytes',
                    $sizeInBytes,
                    $bytesWritten
                ));
            }
        } finally {
            fclose($destination);
        }

        return new StoredObject(
            key: $key,
            adapter: 'local',
            storedAt: new DateTimeImmutable(),
        );
    }

    public function readStream(string $key)
    {
        $fullPath = $this->getFullPath($key);

        if (!file_exists($fullPath)) {
            throw new RuntimeException(sprintf('File not found: %s', $key));
        }

        if (!is_readable($fullPath)) {
            throw new RuntimeException(sprintf('File not readable: %s', $key));
        }

        $stream = fopen($fullPath, 'r');

        if ($stream === false) {
            throw new RuntimeException(sprintf('Failed to open file for reading: %s', $key));
        }

        return $stream;
    }

    public function delete(string $key): void
    {
        $fullPath = $this->getFullPath($key);

        if (!file_exists($fullPath)) {
            // Idempotent: deleting non-existent file is success
            return;
        }

        if (!unlink($fullPath)) {
            throw new RuntimeException(sprintf('Failed to delete file: %s', $key));
        }
    }

    public function exists(string $key): bool
    {
        $fullPath = $this->getFullPath($key);

        return file_exists($fullPath);
    }

    public function url(string $key): ?string
    {
        // Local filesystem has no public URLs
        return null;
    }

    /**
     * Get absolute filesystem path from storage key.
     *
     * @param string $key Opaque storage key (e.g., "files/abc123/photo.jpg")
     *
     * @return string Absolute filesystem path
     */
    private function getFullPath(string $key): string
    {
        // Normalize key (remove leading slash if present)
        $normalizedKey = ltrim($key, '/');

        // Prevent empty keys
        if (empty($normalizedKey)) {
            throw new InvalidArgumentException('Storage key cannot be empty');
        }

        // Prevent excessively long keys (filesystem limits)
        // Max path length: 4096 chars on Linux, but we reserve space for basePath
        if (strlen($normalizedKey) > $this->maxKeyLength) {
            throw new InvalidArgumentException(sprintf(
                'Storage key too long (max %d chars): %d chars',
                $this->maxKeyLength,
                strlen($normalizedKey)
            ));
        }

        // Check each path component doesn't exceed max length (filesystem limit: 255 chars)
        $components = explode('/', $normalizedKey);
        foreach ($components as $component) {
            if (strlen($component) > $this->maxComponentLength) {
                throw new InvalidArgumentException(sprintf(
                    'Path component too long (max %d chars): "%s"',
                    $this->maxComponentLength,
                    $component
                ));
            }
        }

        // Prevent directory traversal attacks
        if (str_contains($normalizedKey, '..')) {
            throw new InvalidArgumentException(sprintf('Invalid key (directory traversal): %s', $key));
        }

        // Prevent control characters (security: avoid injection attacks)
        // \x00-\x1F: ASCII control characters, \x7F: DEL
        if (preg_match('/[\x00-\x1F\x7F]/', $normalizedKey)) {
            throw new InvalidArgumentException(sprintf('Invalid key (control characters not allowed): %s', $key));
        }

        return $this->basePath.'/'.$normalizedKey;
    }
}
