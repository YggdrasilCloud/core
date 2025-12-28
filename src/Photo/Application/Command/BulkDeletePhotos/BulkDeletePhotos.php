<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\BulkDeletePhotos;

/**
 * Command to delete multiple photos in a single operation.
 *
 * Supports partial success: photos that can be deleted will be deleted,
 * and failures will be reported individually without failing the entire operation.
 */
final readonly class BulkDeletePhotos
{
    /**
     * @param list<string> $photoIds UUIDs of photos to delete
     */
    public function __construct(
        public array $photoIds,
    ) {}
}
