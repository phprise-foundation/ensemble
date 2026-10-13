<?php

declare(strict_types=1);

namespace App\Domain\Workflow;

use InvalidArgumentException;

final readonly class ColumnId
{
    public function __construct(private string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Column id must not be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ColumnId $other): bool
    {
        return $this->value === $other->value;
    }
}
