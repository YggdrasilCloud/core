<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoThumbnail;

final readonly class GetPhotoThumbnailQuery
{
    public function __construct(
        public string $photoId,
    ) {}
}
