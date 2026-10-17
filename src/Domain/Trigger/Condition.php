<?php

declare(strict_types=1);

namespace App\Domain\Trigger;

final readonly class Condition
{
    public function __construct(
        private string $field,
        private Operator $op,
        private int|string|bool $value,
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }

    public function matches(mixed $actual): bool
    {
        return match ($this->op) {
            Operator::Eq => $actual === $this->value,
            Operator::Ne => $actual !== $this->value,
            Operator::Gte => (int) $actual >= (int) $this->value,
            Operator::Lte => (int) $actual <= (int) $this->value,
            Operator::In => in_array($actual, (array) $this->value, true),
        };
    }
}
