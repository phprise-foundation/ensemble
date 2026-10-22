<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteWorkflow
{
    public function __construct(
        private WorkflowRepository $workflows,
        private IssueTypeRepository $issueTypes,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, WorkflowId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $workflow = $this->workflows->find($workspaceId, $id) ?? throw NotFoundException::workflow();
        if ($this->issueTypes->countForWorkflow($id) > 0) {
            throw ConflictException::inUse('workflow');
        }

        $this->workflows->delete($workflow);
    }
}
