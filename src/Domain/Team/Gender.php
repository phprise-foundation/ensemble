<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case NotInformed = 'not_informed';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationException('gender', 'Gender must be female, male or not_informed.');
    }
}
