<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Exception\ValidationException;

final readonly class SecretValue
{
    public function __construct(private string $value)
    {
        if ($value === '' || mb_strlen($value) > 8192) {
            throw new ValidationException('value', 'Secret value must have 1 to 8192 characters.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
