<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use Symfony\Component\Uid\Uuid;

final readonly class PhotoId
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $id): self
    {
        if (!Uuid::isValid($id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid PhotoId: %s', $id));
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
