<?php

declare(strict_types=1);

namespace App\Domain\Trigger;

use InvalidArgumentException;

final readonly class TriggerId
{
    public function __construct(private string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Trigger id must not be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
