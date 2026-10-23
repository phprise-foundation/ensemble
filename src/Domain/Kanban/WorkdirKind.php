<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

enum WorkdirKind: string
{
    case Scratch = 'scratch';
    case Repository = 'repository';
}
