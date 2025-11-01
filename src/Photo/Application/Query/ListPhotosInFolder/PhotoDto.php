<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListPhotosInFolder;

final readonly class PhotoDto
{
    public function __construct(
        public string $id,
        public string $fileName,
        public string $storageKey,
        public string $mimeType,
        public int $sizeInBytes,
        public string $uploadedAt,
        public ?string $takenAt,
        public string $fileUrl,
        public ?string $thumbnailUrl,
    ) {}
}
