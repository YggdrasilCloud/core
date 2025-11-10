<?php

declare(strict_types=1);

namespace App\Photo\Domain\Service;

use App\Photo\Domain\Model\MimeTypeCollection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function sprintf;

final readonly class FileValidator
{
    private readonly MimeTypeCollection $allowedMimeTypes;

    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        private int $maxFileSize,
        array $allowedMimeTypes,
    ) {
        $this->allowedMimeTypes = MimeTypeCollection::fromStrings($allowedMimeTypes);
    }

    public function validate(UploadedFile $file): ?string
    {
        // Check file size (skip if -1 = unlimited)
        if ($this->maxFileSize !== -1 && $file->getSize() > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / 1024 / 1024, 2);

            return sprintf('File size exceeds maximum allowed size of %s MB', $maxSizeMB);
        }

        // Check MIME type (skip if empty collection = no restriction)
        if (!$this->allowedMimeTypes->isEmpty()) {
            $mimeType = $file->getMimeType();
            if ($mimeType === null || !$this->allowedMimeTypes->contains($mimeType)) {
                return sprintf(
                    'File type not allowed. Allowed types: %s',
                    $this->allowedMimeTypes->toCommaSeparatedString()
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
