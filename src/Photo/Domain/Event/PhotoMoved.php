<?php

declare(strict_types=1);

namespace App\Photo\Domain\Event;

final readonly class PhotoMoved
{
    public function __construct(
        public string $photoId,
        public string $fromFolderId,
        public string $toFolderId,
    ) {}
}
