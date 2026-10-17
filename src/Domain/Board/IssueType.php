<?php

declare(strict_types=1);

namespace App\Domain\Board;

enum IssueType: string
{
    case Idea = 'idea';
    case Rfc = 'rfc';
    case Task = 'task';
    case Subtask = 'subtask';
}
