<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\MoveFolder;

/**
 * Command to move a folder to a new parent.
 *
 * Moves both the folder entity in the database and the physical directory
 * on the filesystem.
 */
final readonly class MoveFolderCommand
{
    public function __construct(
        public string $folderId,
        public ?string $targetParentId,
    ) {}
}
