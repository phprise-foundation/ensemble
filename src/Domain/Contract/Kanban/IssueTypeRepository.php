<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;

interface IssueTypeRepository
{
    public function add(IssueType $issueType): void;

    public function update(IssueType $issueType): void;

    public function delete(IssueType $issueType): void;

    public function find(WorkspaceId $workspaceId, IssueTypeId $id): ?IssueType;

    public function findByKey(WorkflowId $workflowId, Slug $key): ?IssueType;

    /** @return list<IssueType> */
    public function forWorkflow(WorkspaceId $workspaceId, WorkflowId $workflowId): array;

    public function countForWorkflow(WorkflowId $workflowId): int;
}
