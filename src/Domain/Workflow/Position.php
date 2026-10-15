<?php

declare(strict_types=1);

namespace App\Domain\Workflow;

use InvalidArgumentException;

final readonly class Position
{
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Position must not be negative.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(Position $other): bool
    {
        return $this->value === $other->value;
    }

    public function before(Position $other): bool
    {
        return $this->value < $other->value;
    }
}
