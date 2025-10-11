<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

final readonly class FolderName
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $name): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Folder name cannot be empty');
        }

        if (strlen($trimmed) > 255) {
            throw new \InvalidArgumentException('Folder name cannot exceed 255 characters');
        }

        // Interdire les caractères interdits dans les noms de fichiers
        if (preg_match('/[\/\\\:\*\?\"\<\>\|]/', $trimmed)) {
            throw new \InvalidArgumentException('Folder name contains invalid characters');
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
