<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListTransitions
{
    public function __construct(
        private WorkflowRepository $workflows,
        private TransitionRepository $transitions,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Transition> */
    public function execute(User $actor, WorkspaceId $workspaceId, WorkflowId $workflowId): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $this->workflows->find($workspaceId, $workflowId) ?? throw NotFoundException::workflow();

        return $this->transitions->forWorkflow($workspaceId, $workflowId);
    }
}
