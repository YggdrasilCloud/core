<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\CreateFolder;

final readonly class CreateFolderCommand
{
    public function __construct(
        public string $folderId,
        public string $folderName,
        public string $ownerId,
        public ?string $parentId = null,
    ) {}
}
