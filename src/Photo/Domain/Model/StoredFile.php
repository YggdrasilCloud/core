<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use InvalidArgumentException;

use function sprintf;

final readonly class StoredFile
{
    private function __construct(
        private string $storagePath,
        private string $mimeType,
        private int $sizeInBytes,
        private ?string $thumbnailPath = null,
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

    public function storagePath(): string
    {
        return $this->storagePath;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeInBytes(): int
    {
        return $this->sizeInBytes;
    }

    public function thumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }
}
