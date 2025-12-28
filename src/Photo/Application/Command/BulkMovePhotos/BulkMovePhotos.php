<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\BulkMovePhotos;

/**
 * Command to move multiple photos to a target folder in a single operation.
 *
 * Supports partial success: photos that can be moved will be moved,
 * and failures will be reported individually without failing the entire operation.
 */
final readonly class BulkMovePhotos
{
    /**
     * @param list<string> $photoIds       UUIDs of photos to move
     * @param string       $targetFolderId UUID of the destination folder
     */
    public function __construct(
        public array $photoIds,
        public string $targetFolderId,
    ) {}
}
