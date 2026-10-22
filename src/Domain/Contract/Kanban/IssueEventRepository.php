<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueId;
use App\Domain\Tenancy\WorkspaceId;

interface IssueEventRepository
{
    public function add(IssueEvent $event): void;

    /** @return list<IssueEvent> */
    public function forIssue(WorkspaceId $workspaceId, IssueId $issueId): array;
}
