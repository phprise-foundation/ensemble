<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Team\RoleIds;
use App\Domain\Tenancy\WorkspaceId;

interface StatusRepository
{
    public function add(Status $status): void;

    public function update(Status $status): void;

    public function delete(Status $status): void;

    public function find(WorkspaceId $workspaceId, StatusId $id): ?Status;

    public function findByKey(IssueTypeId $issueTypeId, Slug $key): ?Status;

    /** @return list<Status> */
    public function forIssueType(WorkspaceId $workspaceId, IssueTypeId $issueTypeId): array;

    /** @return list<Status> */
    public function forWorkflow(WorkspaceId $workspaceId, WorkflowId $workflowId): array;

    public function countForIssueType(IssueTypeId $issueTypeId): int;

    public function countByType(IssueTypeId $issueTypeId, StatusType $type): int;

    /**
     * @param RoleIds $roleIds
     * @return list<Status>
     */
    public function ownedByRoles(WorkspaceId $workspaceId, RoleIds $roleIds): array;
}
