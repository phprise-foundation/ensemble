<?php

declare(strict_types=1);

namespace App\Domain\Runner;

enum UsagePlan: string
{
    case Free = 'free';

    case Paid = 'paid';
}
