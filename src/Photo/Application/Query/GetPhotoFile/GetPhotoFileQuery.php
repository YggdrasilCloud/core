<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoFile;

final readonly class GetPhotoFileQuery
{
    public function __construct(
        public string $photoId,
    ) {}
}
