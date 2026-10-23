<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Exception\ValidationException;

enum Provider: string
{
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationException('provider', 'Provider must be github, gitlab or bitbucket.');
    }
}
