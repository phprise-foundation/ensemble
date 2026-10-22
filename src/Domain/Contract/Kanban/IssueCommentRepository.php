<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\IssueComment;
use App\Domain\Kanban\IssueId;
use App\Domain\Tenancy\WorkspaceId;

interface IssueCommentRepository
{
    public function add(IssueComment $comment): void;

    /** @return list<IssueComment> */
    public function forIssue(WorkspaceId $workspaceId, IssueId $issueId): array;
}
