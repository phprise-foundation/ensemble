<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use InvalidArgumentException;

final readonly class AgentUsage
{
    public function __construct(private int $requests, private int $tokens)
    {
        if ($requests < 0 || $tokens < 0) {
            throw new InvalidArgumentException('Usage counters must not be negative.');
        }
    }

    public function requests(): int
    {
        return $this->requests;
    }

    public function tokens(): int
    {
        return $this->tokens;
    }
}
