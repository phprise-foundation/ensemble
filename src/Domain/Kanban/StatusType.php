<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;

enum StatusType: string
{
    case Initial = 'initial';
    case Transitional = 'transitional';
    case Final = 'final';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationException('type', 'Status type must be initial, transitional or final.');
    }
}
