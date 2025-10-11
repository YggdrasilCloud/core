<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoFile;

final readonly class FileResponseModel
{
    public function __construct(
        public string $filePath,
        public string $mimeType,
        public int $cacheMaxAge,
    ) {
    }
}
