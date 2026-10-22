<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\BoardFilters;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ValidateBoardFilters
{
    public function __construct(
        private IssueTypeRepository $issueTypes,
        private StatusRepository $statuses,
    ) {
    }

    public function execute(WorkspaceId $workspaceId, WorkflowId $workflowId, BoardFilters $filters): void
    {
        foreach ($filters->issueTypeIds() as $issueTypeId) {
            $this->guardIssueType($workspaceId, $workflowId, $issueTypeId);
        }
        foreach ($filters->statusIds() as $statusId) {
            $this->guardStatus($workspaceId, $workflowId, $statusId);
        }
    }

    private function guardIssueType(WorkspaceId $workspaceId, WorkflowId $workflowId, IssueTypeId $issueTypeId): void
    {
        $issueType = $this->issueTypes->find($workspaceId, $issueTypeId);
        if ($issueType === null || $issueType->workflowId()->value() !== $workflowId->value()) {
            throw new ValidationException('filterIssueTypeIds', 'Every filtered issue type must belong to the board workflow.');
        }
    }

    private function guardStatus(WorkspaceId $workspaceId, WorkflowId $workflowId, StatusId $statusId): void
    {
        $status = $this->statuses->find($workspaceId, $statusId);
        if ($status === null || $status->workflowId()->value() !== $workflowId->value()) {
            throw new ValidationException('filterStatusIds', 'Every filtered status must belong to the board workflow.');
        }
    }
}
