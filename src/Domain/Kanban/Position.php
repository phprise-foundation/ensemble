<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;

final readonly class Position
{
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new ValidationException('position', 'Position must not be negative.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }
}
