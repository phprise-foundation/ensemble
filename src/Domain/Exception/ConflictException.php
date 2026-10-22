<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class ConflictException extends RuntimeException
{
    public static function duplicateLogin(): self
    {
        return new self('login already in use');
    }

    public static function duplicateWorkspace(): self
    {
        return new self('workspace already exists');
    }

    public static function lastOwner(): self
    {
        return new self('a workspace must keep at least one owner');
    }
}
