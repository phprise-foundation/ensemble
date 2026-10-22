<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Exception\ValidationException;

final readonly class Login
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $normalized)) {
            throw new ValidationException('login', 'Login must have 3 to 32 characters and use letters, digits, dot, dash or underscore.');
        }

        $this->value = $normalized;
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
