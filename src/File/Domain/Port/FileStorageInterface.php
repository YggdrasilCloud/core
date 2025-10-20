<?php

declare(strict_types=1);

namespace App\File\Domain\Port;

use App\File\Domain\Model\StoredObject;
use InvalidArgumentException;
use RuntimeException;

/**
 * Port for file storage abstraction.
 *
 * Minimal API with 5 essential operations.
 * Implementations can be local filesystem, S3, FTP, etc.
 */
interface FileStorageInterface
{
    /**
     * Save a file stream to storage.
     *
     * @param resource $stream      File stream to save
     * @param string   $key         Opaque key (e.g., "files/abc123/photo.jpg")
     * @param string   $mimeType    MIME type (e.g., "image/jpeg")
     * @param int      $sizeInBytes File size in bytes
     *
     * @return StoredObject Metadata about the stored file
     *
     * @throws InvalidArgumentException If stream is invalid
     * @throws RuntimeException         If storage operation fails
     */
    public function save($stream, string $key, string $mimeType, int $sizeInBytes): StoredObject;

    /**
     * Read a file as a stream.
     *
     * @param string $key Opaque key identifying the file
     *
     * @return resource File stream
     *
     * @throws RuntimeException If file does not exist or cannot be read
     */
    public function readStream(string $key);

    /**
     * Delete a file from storage.
     *
     * @param string $key Opaque key identifying the file
     *
     * @throws RuntimeException If deletion fails
     */
    public function delete(string $key): void;

    /**
     * Check if a file exists in storage.
     *
     * @param string $key Opaque key identifying the file
     */
    public function exists(string $key): bool;

    /**
     * Get public URL for a file (if applicable).
     *
     * Returns null for storage adapters without public URLs (e.g., local filesystem).
     * Returns a full URL for cloud storage (e.g., S3 presigned URL).
     *
     * @param string $key Opaque key identifying the file
     *
     * @return null|string Public URL or null if not applicable
     */
    public function url(string $key): ?string;
}
