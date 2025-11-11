<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\RenameFolder;

/**
 * Command to rename a folder.
 *
 * Renames both the folder entity in the database and the physical directory
 * on the filesystem.
 */
final readonly class RenameFolderCommand
{
    public function __construct(
        public string $folderId,
        public string $newFolderName,
    ) {}
}
