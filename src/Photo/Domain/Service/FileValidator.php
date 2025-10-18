<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

use function count;
use function in_array;
use function sprintf;

final readonly class FileValidator
{
    private readonly array $cleanedMimeTypes;

    public function __construct(
        private int $maxFileSize,
        array $allowedMimeTypes,
    ) {
        // Filter out empty strings from CSV parsing
        $this->cleanedMimeTypes = array_values(array_filter($allowedMimeTypes, static fn ($type) => $type !== ''));
    }

    public function validate(UploadedFile $file): ?string
    {
        // Check file size (skip if -1 = unlimited)
        if ($this->maxFileSize !== -1 && $file->getSize() > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / 1024 / 1024, 2);

            return sprintf('File size exceeds maximum allowed size of %s MB', $maxSizeMB);
        }

        // Check MIME type (skip if empty array = no restriction)
        if (count($this->cleanedMimeTypes) > 0) {
            $mimeType = $file->getMimeType();
            if ($mimeType === null || !in_array($mimeType, $this->cleanedMimeTypes, true)) {
                return sprintf(
                    'File type not allowed. Allowed types: %s',
                    implode(', ', $this->cleanedMimeTypes)
                );
            }
        }

        return null;
    }

    public function sanitizeFilename(string $filename): string
    {
        // Get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        // Remove only problematic characters for file systems, keep accents
        // Replace forbidden characters with underscores: / \ : * ? " < > |
        $basename = preg_replace('/[\/\\\:\*\?\"\<\>\|]/', '_', $basename) ?? $basename;

        // Replace multiple spaces/underscores with single underscore
        $basename = preg_replace('/[\s_]+/', '_', $basename) ?? $basename;

        // Trim underscores from start and end
        $basename = trim($basename, '_');

        // Limit length and ensure it's not empty
        $basename = substr($basename ?: 'file', 0, 100);

        return $basename.($extension ? '.'.$extension : '');
    }
}
