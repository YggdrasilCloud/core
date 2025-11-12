<?php

declare(strict_types=1);

namespace App\File\Domain\Model;

use function preg_match;
use function sprintf;

/**
 * Value Object representing the parts of a filename (base name and extension).
 *
 * Examples:
 * - "photo.jpg" → baseName: "photo", extension: ".jpg"
 * - "archive.tar.gz" → baseName: "archive.tar", extension: ".gz"
 * - "README" → baseName: "README", extension: ""
 */
final readonly class FileNameParts
{
    private function __construct(
        public string $baseName,
        public string $extension,
    ) {}

    /**
     * Creates FileNameParts from a filename.
     *
     * Splits the filename at the last dot, treating everything before as the base name
     * and everything after as the extension.
     *
     * @param string $fileName The filename to parse
     */
    public static function fromFileName(string $fileName): self
    {
        // Match the last dot and everything after it
        if (preg_match('/^(.+)(\.[^.]+)$/', $fileName, $matches) === 1) {
            return new self($matches[1], $matches[2]);
        }

        // No extension found
        return new self($fileName, '');
    }

    /**
     * Checks if the filename has an extension.
     */
    public function hasExtension(): bool
    {
        return $this->extension !== '';
    }

    /**
     * Creates a new FileNameParts with a numeric suffix appended to the base name.
     *
     * Example: "photo" + 1 → "photo (1)"
     *
     * @param int $suffix The numeric suffix to append
     *
     * @return self A new instance with the suffix applied
     */
    public function withSuffix(int $suffix): self
    {
        return new self(
            sprintf('%s (%d)', $this->baseName, $suffix),
            $this->extension
        );
    }

    /**
     * Reconstructs the full filename from parts.
     *
     * @return string The complete filename
     */
    public function toString(): string
    {
        return $this->baseName.$this->extension;
    }
}
