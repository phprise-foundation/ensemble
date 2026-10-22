<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListStatuses
{
    public function __construct(
        private WorkflowRepository $workflows,
        private IssueTypeRepository $issueTypes,
        private StatusRepository $statuses,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Status> */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
        ?IssueTypeId $issueTypeId,
    ): array {
        $this->guard->assertMember($actor, $workspaceId);
        $this->workflows->find($workspaceId, $workflowId) ?? throw NotFoundException::workflow();
        if ($issueTypeId === null) {
            return $this->statuses->forWorkflow($workspaceId, $workflowId);
        }
        $issueType = $this->issueTypes->find($workspaceId, $issueTypeId) ?? throw NotFoundException::issueType();
        if ($issueType->workflowId()->value() !== $workflowId->value()) {
            throw NotFoundException::issueType();
        }

        return $this->statuses->forIssueType($workspaceId, $issueTypeId);
    }
}
