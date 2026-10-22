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

    public static function workflow(): self
    {
        return new self('workflow not found');
    }

    public static function issueType(): self
    {
        return new self('issue type not found');
    }

    public static function field(): self
    {
        return new self('field not found');
    }

    public static function status(): self
    {
        return new self('status not found');
    }

    public static function transition(): self
    {
        return new self('transition not found');
    }

    public static function board(): self
    {
        return new self('board not found');
    }

    public static function column(): self
    {
        return new self('column not found');
    }

    public static function issue(): self
    {
        return new self('issue not found');
    }
}
