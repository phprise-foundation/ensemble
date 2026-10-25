<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

enum IssueEventType: string
{
    case Created = 'created';
    case Status = 'status';
    case Assignee = 'assignee';
    case Summary = 'summary';
    case Description = 'description';
    case Comment = 'comment';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Status;
    }
}
