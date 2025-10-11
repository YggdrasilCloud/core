<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use Symfony\Component\Uid\Uuid;

final readonly class UserId
{
    private function __construct(private string $value) {}

    public static function fromString(string $id): self
    {
        if (!Uuid::isValid($id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid UserId: %s', $id));
        }

        return new self($id);
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
