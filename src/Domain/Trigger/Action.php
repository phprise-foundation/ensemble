<?php

declare(strict_types=1);

namespace App\Domain\Trigger;

final readonly class Action
{
    public function __construct(
        private string $field,
        private int|string|bool $value,
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }

    public function value(): int|string|bool
    {
        return $this->value;
    }
}
