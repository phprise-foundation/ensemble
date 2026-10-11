<?php

declare(strict_types=1);

namespace App\Domain\Board;

final readonly class ItemId
{
    public function __construct(private int $value)
    {
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(ItemId $other): bool
    {
        return $this->value === $other->value;
    }
}
