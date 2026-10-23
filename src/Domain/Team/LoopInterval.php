<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;

final readonly class LoopInterval
{
    private const MINIMUM = 30;

    private const MAXIMUM = 86_400;

    public function __construct(private int $value)
    {
        if ($value < self::MINIMUM || $value > self::MAXIMUM) {
            throw new ValidationException('interval', sprintf(
                'Loop interval must be between %d and %d seconds.',
                self::MINIMUM,
                self::MAXIMUM,
            ));
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
