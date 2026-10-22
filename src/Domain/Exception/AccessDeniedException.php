<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class AccessDeniedException extends RuntimeException
{
    public static function forWorkspace(): self
    {
        return new self('access denied for this workspace');
    }

    public static function adminOnly(): self
    {
        return new self('admin access required');
    }
}
