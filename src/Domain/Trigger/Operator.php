<?php

declare(strict_types=1);

namespace App\Domain\Trigger;

enum Operator: string
{
    case Eq = 'eq';
    case Ne = 'ne';
    case Gte = 'gte';
    case Lte = 'lte';
    case In = 'in';
}
