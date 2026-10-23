<?php

declare(strict_types=1);

namespace App\Domain\Team;

use InvalidArgumentException;

final readonly class TeamId
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $value)) {
            throw new InvalidArgumentException('Team id must be a 32-character hexadecimal string.');
        }
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
