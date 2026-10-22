<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class NotFoundException extends RuntimeException
{
    public static function user(): self
    {
        return new self('user not found');
    }

    public static function workspace(): self
    {
        return new self('workspace not found');
    }

    public static function membership(): self
    {
        return new self('membership not found');
    }
}
