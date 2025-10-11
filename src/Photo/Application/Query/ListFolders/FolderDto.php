<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListFolders;

final readonly class FolderDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $createdAt,
    ) {
    }
}
