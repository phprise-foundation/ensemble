<?php

declare(strict_types=1);

namespace App\Domain\Agent;

use InvalidArgumentException;

final readonly class AgentId
{
    public function __construct(private string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Agent id must not be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(AgentId $other): bool
    {
        return $this->value === $other->value;
    }
}
