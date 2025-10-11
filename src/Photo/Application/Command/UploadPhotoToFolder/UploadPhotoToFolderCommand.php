<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\UploadPhotoToFolder;

final class UploadPhotoToFolderCommand
{
    /**
     * @param resource $fileStream
     */
    public function __construct(
        public readonly string $photoId,
        public readonly string $folderId,
        public readonly string $ownerId,
        public readonly string $fileName,
        /** @var resource */
        public $fileStream,
        public readonly string $mimeType,
        public readonly int $sizeInBytes,
    ) {
    }
}
