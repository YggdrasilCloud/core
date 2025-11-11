<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service;

use App\File\Domain\Service\FileNameSanitizer;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use RuntimeException;

use function array_reverse;
use function implode;

/**
 * Builds filesystem paths from folder hierarchy.
 *
 * Constructs storage paths that reflect the actual folder names and hierarchy,
 * enabling SMB/CIFS mounting and human-readable directory structures.
 *
 * Example:
 * - Root folder "Vacances" → "photos/Vacances"
 * - Subfolder "Été 2024" (parent: "Vacances") → "photos/Vacances/Été 2024"
 */
final readonly class FileSystemPathBuilder
{
    private const BASE_PATH = 'photos';

    /**
     * Maximum depth to prevent infinite loops in case of circular references.
     */
    private const MAX_DEPTH = 100;

    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FileNameSanitizer $sanitizer,
    ) {}

    /**
     * Builds the full filesystem path for a folder.
     *
     * Walks up the folder hierarchy to the root and constructs the path
     * with sanitized folder names.
     *
     * @param Folder $folder The folder to build the path for
     *
     * @return string The full path (e.g., "photos/Vacances/Été 2024")
     *
     * @throws RuntimeException If circular reference detected or max depth exceeded
     */
    public function buildFolderPath(Folder $folder): string
    {
        $segments = [];
        $current = $folder;
        $depth = 0;

        // Walk up the hierarchy, collecting folder names
        while ($current !== null) {
            if ($depth >= self::MAX_DEPTH) {
                throw new RuntimeException('Maximum folder depth exceeded - possible circular reference');
            }

            // Sanitize the folder name for filesystem compatibility
            $sanitizedName = $this->sanitizer->sanitize($current->name()->toString());
            $segments[] = $sanitizedName;

            // Move to parent folder
            $parentId = $current->parentId();
            if ($parentId === null) {
                break; // Reached root folder
            }

            $current = $this->folderRepository->findById($parentId);
            if ($current === null) {
                throw new RuntimeException(sprintf('Parent folder not found: %s', $parentId->toString()));
            }

            ++$depth;
        }

        // Reverse to get root → leaf order
        $segments = array_reverse($segments);

        // Prepend base path
        return self::BASE_PATH.'/'.implode('/', $segments);
    }

    /**
     * Builds the full filesystem path for a file within a folder.
     *
     * @param Folder $folder   The folder containing the file
     * @param string $fileName The filename (will be sanitized)
     *
     * @return string The full path (e.g., "photos/Vacances/plage.jpg")
     */
    public function buildFilePath(Folder $folder, string $fileName): string
    {
        $folderPath = $this->buildFolderPath($folder);
        $sanitizedFileName = $this->sanitizer->sanitize($fileName);

        return $folderPath.'/'.$sanitizedFileName;
    }
}
