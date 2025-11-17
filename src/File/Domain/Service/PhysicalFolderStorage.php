<?php

declare(strict_types=1);

namespace App\File\Domain\Service;

use RuntimeException;

use function is_dir;
use function mkdir;
use function rename;
use function rmdir;
use function sprintf;

/**
 * Handles physical folder storage operations for local filesystem.
 *
 * Provides directory operations that complement FileStorageInterface (which handles files).
 * Enables creating, renaming, and deleting physical folders on the filesystem.
 *
 * NOTE: This service is specific to local filesystem storage.
 * For cloud storage (S3, etc.), folders are virtual and this service would be a no-op.
 */
final readonly class PhysicalFolderStorage
{
    public function __construct(
        private string $basePath,
    ) {}

    /**
     * Creates a directory at the given path.
     *
     * The path is relative to the storage base path.
     * Creates all parent directories if they don't exist.
     *
     * @param string $relativePath Path relative to base (e.g., "photos/Vacances/Été 2024")
     *
     * @throws RuntimeException If directory creation fails
     */
    public function createDirectory(string $relativePath): void
    {
        $fullPath = $this->getFullPath($relativePath);

        if (is_dir($fullPath)) {
            // Directory already exists, nothing to do
            return;
        }

        if (!mkdir($fullPath, 0755, true) && !is_dir($fullPath)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $fullPath));
        }
    }

    /**
     * Renames/moves a directory.
     *
     * Both paths are relative to the storage base path.
     *
     * @param string $oldPath Old path relative to base
     * @param string $newPath New path relative to base
     *
     * @throws RuntimeException If rename fails or old directory doesn't exist
     */
    public function renameDirectory(string $oldPath, string $newPath): void
    {
        $oldFullPath = $this->getFullPath($oldPath);
        $newFullPath = $this->getFullPath($newPath);

        if (!is_dir($oldFullPath)) {
            throw new RuntimeException(sprintf('Directory does not exist: %s', $oldFullPath));
        }

        if (is_dir($newFullPath)) {
            throw new RuntimeException(sprintf('Target directory already exists: %s', $newFullPath));
        }

        // Ensure parent directory of new path exists
        $newParentDir = dirname($newFullPath);
        if (!is_dir($newParentDir)) {
            if (!mkdir($newParentDir, 0755, true) && !is_dir($newParentDir)) {
                throw new RuntimeException(sprintf('Failed to create parent directory: %s', $newParentDir));
            }
        }

        if (!rename($oldFullPath, $newFullPath)) {
            throw new RuntimeException(sprintf(
                'Failed to rename directory from %s to %s',
                $oldFullPath,
                $newFullPath,
            ));
        }
    }

    /**
     * Removes an empty directory.
     *
     * IMPORTANT: This only removes empty directories. If the directory contains
     * files or subdirectories, this will throw an exception. Callers must ensure
     * all files are deleted first.
     *
     * @param string $relativePath Path relative to base
     *
     * @throws RuntimeException If directory is not empty or removal fails
     */
    public function removeEmptyDirectory(string $relativePath): void
    {
        $fullPath = $this->getFullPath($relativePath);

        if (!is_dir($fullPath)) {
            // Directory doesn't exist, nothing to do
            return;
        }

        if (!rmdir($fullPath)) {
            throw new RuntimeException(sprintf(
                'Failed to remove directory (may not be empty): %s',
                $fullPath,
            ));
        }
    }

    /**
     * Checks if a directory exists.
     *
     * @param string $relativePath Path relative to base
     */
    public function directoryExists(string $relativePath): bool
    {
        $fullPath = $this->getFullPath($relativePath);

        return is_dir($fullPath);
    }

    /**
     * Converts relative path to full filesystem path.
     *
     * @param string $relativePath Path relative to storage base
     *
     * @return string Full filesystem path
     */
    private function getFullPath(string $relativePath): string
    {
        $normalizedPath = ltrim($relativePath, '/');

        return $this->basePath.'/'.$normalizedPath;
    }
}
