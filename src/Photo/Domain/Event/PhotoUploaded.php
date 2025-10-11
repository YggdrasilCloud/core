<?php

declare(strict_types=1);

namespace App\Photo\Domain\Event;

final readonly class PhotoUploaded
{
    public function __construct(
        public string $photoId,
        public string $folderId,
        public string $ownerId,
        public string $fileName,
        public string $storagePath,
        public string $mimeType,
        public int $sizeInBytes,
    ) {}
}
