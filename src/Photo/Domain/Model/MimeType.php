<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use InvalidArgumentException;

use function sprintf;
use function strlen;
use function trim;

/**
 * Value Object representing a MIME type (e.g., "image/jpeg", "image/png").
 */
final readonly class MimeType
{
    private string $value;

    private function __construct(
        string $value,
    ) {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('MIME type cannot be empty');
        }

        // Basic validation: must contain a slash
        if (!str_contains($trimmed, '/')) {
            throw new InvalidArgumentException(sprintf('Invalid MIME type format: %s', $trimmed));
        }

        if (strlen($trimmed) > 255) {
            throw new InvalidArgumentException('MIME type too long');
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
