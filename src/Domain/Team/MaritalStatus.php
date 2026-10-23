<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case Other = 'other';

    public static function fromString(string $value): self
    {
        $candidate = self::tryFrom(strtolower($value));
        if ($candidate === null) {
            throw self::invalid($value);
        }

        return $candidate;
    }

    private static function invalid(string $value): ValidationException
    {
        return new ValidationException('maritalStatus', sprintf(
            'Marital status must be one of: single, married, divorced, widowed, other. Received "%s".',
            $value,
        ));
    }
}
