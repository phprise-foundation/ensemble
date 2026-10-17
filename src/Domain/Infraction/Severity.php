<?php

declare(strict_types=1);

namespace App\Domain\Infraction;

enum Severity: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Blocking = 'blocking';
}
