<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Exception\ValidationException;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationException('role', 'Role must be owner or member.');
    }
}
