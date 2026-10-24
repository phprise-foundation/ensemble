<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\StatusIds;
use App\Domain\Tenancy\WorkspaceId;

interface IssueRepository
{
    public function add(Issue $issue): void;

    public function update(Issue $issue): void;

    public function find(WorkspaceId $workspaceId, IssueKey $key): ?Issue;

    public function findById(WorkspaceId $workspaceId, IssueId $id): ?Issue;

    /** @return list<Issue> */
    public function forWorkspace(WorkspaceId $workspaceId): array;

    /**
     * @param StatusIds $statusIds
     * @return list<Issue>
     */
    public function forStatuses(WorkspaceId $workspaceId, StatusIds $statusIds): array;

    /** @return list<Issue> */
    public function forChildren(WorkspaceId $workspaceId, IssueKey $parentKey): array;

    /** @return list<Issue> */
    public function withoutWorkdir(): array;
}
