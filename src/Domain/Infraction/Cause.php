<?php

declare(strict_types=1);

namespace App\Domain\Infraction;

enum Cause: string
{
    case Requirement = 'requirement';
    case Design = 'design';
    case Bug = 'bug';
    case TestGap = 'test-gap';
    case Scope = 'scope';
    case Feasibility = 'feasibility';
}
