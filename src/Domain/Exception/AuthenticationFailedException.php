<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class AuthenticationFailedException extends RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('invalid credentials');
    }

    public static function missingToken(): self
    {
        return new self('missing or invalid session token');
    }
}
