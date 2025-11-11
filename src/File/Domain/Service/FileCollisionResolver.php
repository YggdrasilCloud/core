<?php

declare(strict_types=1);

namespace App\File\Domain\Service;

use App\File\Domain\Port\FileStorageInterface;
use RuntimeException;

use function dirname;
use function preg_match;
use function sprintf;

/**
 * Resolves filename collisions by appending numeric suffixes.
 *
 * Uses Windows/macOS-style suffixes: filename.ext, filename (1).ext, filename (2).ext, etc.
 */
final readonly class FileCollisionResolver
{
    /**
     * Maximum attempts to find a unique filename before giving up.
     */
    private const MAX_ATTEMPTS = 1000;

    public function __construct(
        private FileStorageInterface $storage,
    ) {}

    /**
     * Resolves filename collisions by finding the first available numeric suffix.
     *
     * If the file at $proposedKey already exists, tries:
     * - filename (1).ext
     * - filename (2).ext
     * - filename (3).ext
     * ... until an available name is found.
     *
     * @param string $proposedKey The initially proposed storage key
     *
     * @return string The final unique storage key (may be the same if no collision)
     *
     * @throws RuntimeException If unable to find unique name after MAX_ATTEMPTS
     */
    public function resolveUniquePath(string $proposedKey): string
    {
        // If the proposed key doesn't exist, use it directly
        if (!$this->storage->exists($proposedKey)) {
            return $proposedKey;
        }

        // Extract directory, base name, and extension
        $directory = dirname($proposedKey);
        $fileName = basename($proposedKey);
        [$baseName, $extension] = $this->splitFileName($fileName);

        // Try incrementing suffixes until we find an available name
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $newFileName = sprintf('%s (%d)%s', $baseName, $attempt, $extension);
            $newKey = $directory === '.' ? $newFileName : $directory.'/'.$newFileName;

            if (!$this->storage->exists($newKey)) {
                return $newKey;
            }
        }

        throw new RuntimeException(sprintf(
            'Unable to find unique filename after %d attempts for: %s',
            self::MAX_ATTEMPTS,
            $proposedKey,
        ));
    }

    /**
     * Splits a filename into base name and extension.
     *
     * Examples:
     * - "photo.jpg" → ["photo", ".jpg"]
     * - "archive.tar.gz" → ["archive.tar", ".gz"]
     * - "README" → ["README", ""]
     * - "photo (1).jpg" → ["photo (1)", ".jpg"]
     *
     * @return array{string, string} [baseName, extension]
     */
    private function splitFileName(string $fileName): array
    {
        // Match the last dot and everything after it
        if (preg_match('/^(.+)(\.[^.]+)$/', $fileName, $matches) === 1) {
            return [$matches[1], $matches[2]];
        }

        // No extension found
        return [$fileName, ''];
    }
}
