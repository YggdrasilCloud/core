<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\DeleteFolder;

/**
 * Command to delete a folder.
 *
 * Deletes both the folder entity from the database and the physical directory
 * from the filesystem.
 *
 * When recursive is false (default), the folder must be empty.
 * When recursive is true, all photos and subfolders are deleted recursively.
 */
final readonly class DeleteFolderCommand
{
    public function __construct(
        public string $folderId,
        public bool $recursive = false,
    ) {}
}
