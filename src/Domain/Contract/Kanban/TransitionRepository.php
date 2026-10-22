<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;

interface TransitionRepository
{
    public function add(Transition $transition): void;

    public function update(Transition $transition): void;

    public function delete(Transition $transition): void;

    public function find(WorkspaceId $workspaceId, TransitionId $id): ?Transition;

    /** @return list<Transition> */
    public function forWorkflow(WorkspaceId $workspaceId, WorkflowId $workflowId): array;

    public function findExisting(
        WorkflowId $workflowId,
        StatusId $fromStatusId,
        StatusId $toStatusId,
        TransitionActor $actor,
        ?Slug $outcome,
    ): ?Transition;

    public function countForStatus(StatusId $statusId): int;
}
