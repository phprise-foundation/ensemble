<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class InvalidTriggerActionException extends RuntimeException
{
    public static function forField(string $field): self
    {
        return new self(sprintf('Trigger action cannot set field "%s".', $field));
    }
}
