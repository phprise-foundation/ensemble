<?php

declare(strict_types=1);

namespace App\Domain\Security;

use InvalidArgumentException;

final readonly class SessionTokenHash
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new InvalidArgumentException('Session token hash must be a 64-character hexadecimal string.');
        }
    }

    public static function fromToken(string $token): self
    {
        return new self(hash('sha256', $token));
    }

    public function value(): string
    {
        return $this->value;
    }
}
