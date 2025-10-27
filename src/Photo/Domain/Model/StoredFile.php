<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use InvalidArgumentException;

use function sprintf;

final readonly class StoredFile
{
    /**
     * @param string      $storagePath   File storage path
     * @param string      $mimeType      MIME type (must start with 'image/')
     * @param int         $sizeInBytes   File size in bytes
     * @param null|string $thumbnailPath Optional thumbnail path
     */
    private function __construct(
        public string $storagePath,
        public string $mimeType,
        public int $sizeInBytes,
        public ?string $thumbnailPath = null,
    ) {}

    public static function create(
        string $storagePath,
        string $mimeType,
        int $sizeInBytes,
        ?string $thumbnailPath = null,
    ): self {
        if (trim($storagePath) === '') {
            throw new InvalidArgumentException('Storage path cannot be empty');
        }

        if (trim($mimeType) === '') {
            throw new InvalidArgumentException('Mime type cannot be empty');
        }

        if (!str_starts_with($mimeType, 'image/')) {
            throw new InvalidArgumentException(sprintf('Invalid mime type for photo: %s', $mimeType));
        }

        if ($sizeInBytes < 0) {
            throw new InvalidArgumentException('File size cannot be negative');
        }

        return new self($storagePath, $mimeType, $sizeInBytes, $thumbnailPath);
    }
}
