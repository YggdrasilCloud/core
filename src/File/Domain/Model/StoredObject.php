<?php

declare(strict_types=1);

namespace App\File\Domain\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Value Object representing a file stored in storage.
 *
 * Contains metadata about the storage operation result.
 */
final readonly class StoredObject
{
    /**
     * @param string            $key      Opaque key (e.g., "files/abc123/photo.jpg")
     * @param string            $adapter  Storage adapter name (e.g., "local", "s3", "ftp")
     * @param DateTimeImmutable $storedAt Timestamp when file was stored
     */
    public function __construct(
        public string $key,
        public string $adapter,
        public DateTimeImmutable $storedAt,
    ) {
        if (empty($this->key)) {
            throw new InvalidArgumentException('Storage key cannot be empty');
        }

        if (empty($this->adapter)) {
            throw new InvalidArgumentException('Storage adapter name cannot be empty');
        }
    }
}
