<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Tenancy\WorkspaceId;

interface WorkdirRepository
{
    public function add(Workdir $workdir): void;

    public function update(Workdir $workdir): void;

    public function find(WorkspaceId $workspaceId, WorkdirId $id): ?Workdir;

    /**
     * @param list<WorkdirId> $ids
     * @return list<Workdir>
     */
    public function findMany(WorkspaceId $workspaceId, array $ids): array;

    public function findByIssue(WorkspaceId $workspaceId, IssueId $ownerIssueId): ?Workdir;
}
