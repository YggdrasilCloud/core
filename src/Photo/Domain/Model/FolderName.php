<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use InvalidArgumentException;

use function strlen;

final readonly class FolderName
{
    private function __construct(private string $value) {}

    public static function fromString(string $name): self
    {
        $sanitized = self::sanitize($name);

        if ($sanitized === '') {
            throw new InvalidArgumentException('Folder name cannot be empty');
        }

        if (strlen($sanitized) > 255) {
            throw new InvalidArgumentException('Folder name cannot exceed 255 characters');
        }

        return new self($sanitized);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Sanitize folder name by removing problematic characters while keeping accents.
     */
    private static function sanitize(string $name): string
    {
        // Trim whitespace
        $sanitized = trim($name);

        // Replace forbidden characters with underscores: / \ : * ? " < > |
        $sanitized = preg_replace('/[\/\\\:\*\?\"\<\>\|]/', '_', $sanitized) ?? $sanitized;

        // Replace multiple spaces with single space
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;

        // Replace multiple underscores with single underscore
        $sanitized = preg_replace('/_+/', '_', $sanitized) ?? $sanitized;

        // Trim again to remove any leading/trailing spaces or underscores
        return trim($sanitized, " \t\n\r\0\x0B_");
    }
}
