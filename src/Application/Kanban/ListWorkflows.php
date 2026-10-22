<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Kanban\Workflow;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListWorkflows
{
    public function __construct(
        private WorkflowRepository $workflows,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Workflow> */
    public function execute(User $actor, WorkspaceId $workspaceId): array
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->workflows->forWorkspace($workspaceId);
    }
}
