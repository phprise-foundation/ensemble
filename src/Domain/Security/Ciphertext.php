<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Exception\ValidationException;

final readonly class Ciphertext
{
    public function __construct(private string $value)
    {
        if ($value === '' || base64_decode($value, true) === false) {
            throw new ValidationException('value', 'Secret ciphertext must be base64.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
