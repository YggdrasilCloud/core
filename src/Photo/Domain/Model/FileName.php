<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

final readonly class FileName
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $name): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('File name cannot be empty');
        }

        if (strlen($trimmed) > 255) {
            throw new \InvalidArgumentException('File name cannot exceed 255 characters');
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

    public function extension(): string
    {
        return pathinfo($this->value, PATHINFO_EXTENSION);
    }
}
