<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use function pathinfo;

/**
 * Value Object representing parsed path information.
 *
 * Encapsulates the result of pathinfo() with type-safe access to components.
 * Handles optional components (dirname, extension) gracefully.
 */
final readonly class PathInfo
{
    private function __construct(
        private string $dirname,
        private string $filename,
        private ?string $extension,
    ) {}

    public static function fromPath(string $path): self
    {
        $info = pathinfo($path);

        // dirname might not exist or be '.'
        $dirname = $info['dirname'] ?? '.';

        // filename is always present
        $filename = $info['filename'];

        // extension might not exist
        $extension = $info['extension'] ?? null;

        return new self($dirname, $filename, $extension);
    }

    /**
     * Get directory name.
     * Returns '.' if no directory part exists.
     */
    public function getDirname(): string
    {
        return $this->dirname;
    }

    /**
     * Get directory name or empty string if dirname is '.'.
     * Useful for building paths.
     */
    public function getDirnameOrEmpty(): string
    {
        return $this->dirname === '.' ? '' : $this->dirname;
    }

    /**
     * Get filename without extension.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Get file extension without leading dot.
     * Returns null if no extension.
     */
    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /**
     * Check if file has an extension.
     */
    public function hasExtension(): bool
    {
        return $this->extension !== null;
    }

    /**
     * Build filename with given suffix before extension.
     *
     * Example: "photo.jpg" with suffix "_thumb" -> "photo_thumb.jpg"
     */
    public function buildFilenameWithSuffix(string $suffix): string
    {
        if ($this->extension === null) {
            return $this->filename.$suffix;
        }

        return $this->filename.$suffix.'.'.$this->extension;
    }
}
