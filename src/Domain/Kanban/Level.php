<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;

final readonly class Level
{
    public function __construct(private int $value)
    {
        if ($value < 1) {
            throw new ValidationException('level', 'Level must be at least 1.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }
}
