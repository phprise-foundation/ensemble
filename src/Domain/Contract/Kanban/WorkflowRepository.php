<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;

interface WorkflowRepository
{
    public function add(Workflow $workflow): void;

    public function update(Workflow $workflow): void;

    public function delete(Workflow $workflow): void;

    public function find(WorkspaceId $workspaceId, WorkflowId $id): ?Workflow;

    public function findByKey(WorkspaceId $workspaceId, Slug $key): ?Workflow;

    /** @return list<Workflow> */
    public function forWorkspace(WorkspaceId $workspaceId): array;
}
