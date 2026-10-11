<?php

declare(strict_types=1);

namespace App\Domain\Board;

enum Status: string
{
    case Intent = 'intent';
    case Feasibility = 'feasibility';
    case Design = 'design';
    case Plan = 'plan';
    case QaRefinement = 'qa-refinement';
    case Coding = 'coding';
    case Reviewing = 'reviewing';
    case Testing = 'testing';
    case Done = 'done';
}
