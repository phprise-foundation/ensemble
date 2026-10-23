<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\WorkspaceId;

interface IssueRepository
{
    public function add(Issue $issue): void;

    public function update(Issue $issue): void;

    public function find(WorkspaceId $workspaceId, IssueKey $key): ?Issue;

    public function findById(WorkspaceId $workspaceId, IssueId $id): ?Issue;

    /** @return list<Issue> */
    public function forWorkspace(WorkspaceId $workspaceId): array;

    /** @return list<Issue> */
    public function withoutWorkdir(): array;
}
