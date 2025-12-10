<?php

declare(strict_types=1);

namespace App\Photo\Application\Port;

use App\File\Domain\Model\StoredObject;

/**
 * Application port for thumbnail generation.
 *
 * Handles thumbnail generation across different storage adapters:
 * - For local storage: direct filesystem access via ThumbnailGenerator
 * - For remote storage (S3): download → generate → upload
 */
interface ThumbnailServiceInterface
{
    /**
     * Generate a thumbnail for a stored file.
     *
     * @param StoredObject $storedObject The original file metadata
     *
     * @return null|string The storage key of the generated thumbnail, or null if generation failed
     */
    public function generateThumbnail(StoredObject $storedObject): ?string;
}
