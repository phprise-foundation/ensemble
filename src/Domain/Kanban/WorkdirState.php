<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

enum WorkdirState: string
{
    case Scratch = 'scratch';
    case Pending = 'pending';
    case Bound = 'bound';
    case Conflict = 'conflict';
}
