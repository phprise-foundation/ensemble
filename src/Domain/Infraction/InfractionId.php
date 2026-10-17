<?php

declare(strict_types=1);

namespace App\Domain\Infraction;

final readonly class InfractionId
{
    public function __construct(private int $value)
    {
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(InfractionId $other): bool
    {
        return $this->value === $other->value;
    }
}
