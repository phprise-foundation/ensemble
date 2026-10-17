<?php

declare(strict_types=1);

namespace App\Domain\Infraction;

enum InfractionStatus: string
{
    case Declared = 'declared';
    case Upheld = 'upheld';
    case Dismissed = 'dismissed';
}
