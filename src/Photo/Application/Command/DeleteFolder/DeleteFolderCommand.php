<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\DeleteFolder;

/**
 * Command to delete a folder.
 *
 * Deletes both the folder entity from the database and the physical directory
 * from the filesystem.
 *
 * The folder must be empty (no photos, no subfolders) to be deleted.
 */
final readonly class DeleteFolderCommand
{
    public function __construct(
        public string $folderId,
    ) {}
}
