<?php

declare(strict_types=1);

namespace App\Domain\Security;

use InvalidArgumentException;

final readonly class PasswordHash
{
    public function __construct(private string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Password hash must not be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
