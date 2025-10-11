<?php

declare(strict_types=1);

namespace App\Photo\Domain\Event;

final readonly class FolderCreated
{
    public function __construct(
        public string $folderId,
        public string $folderName,
        public string $ownerId,
    ) {}
}
