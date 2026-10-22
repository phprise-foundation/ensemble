<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Exception\ValidationException;

final readonly class PlainPassword
{
    private string $value;

    public function __construct(string $value)
    {
        if (strlen($value) < 8 || strlen($value) > 128) {
            throw new ValidationException('password', 'Password must have 8 to 128 characters.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
