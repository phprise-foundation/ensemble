<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;

enum TransitionActor: string
{
    case User = 'user';
    case Agent = 'agent';
    case Both = 'both';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationException('actor', 'Transition actor must be user, agent or both.');
    }
}
