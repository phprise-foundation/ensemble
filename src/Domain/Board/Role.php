<?php

declare(strict_types=1);

namespace App\Domain\Board;

enum Role: string
{
    case ProductOwner = 'product-owner';
    case SolutionsArchitect = 'solutions-architect';
    case SystemsArchitect = 'systems-architect';
    case FrameworkArchitect = 'framework-architect';
    case TechLead = 'tech-lead';
    case Qa = 'qa';
    case Coder = 'coder';
}
