<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateWorkflow
{
    public function __construct(
        private WorkflowRepository $workflows,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, Slug $key, string $name): Workflow
    {
        $this->guard->assertManager($actor, $workspaceId);
        if ($this->workflows->findByKey($workspaceId, $key) !== null) {
            throw ConflictException::duplicateKey();
        }

        $workflow = new Workflow(WorkflowId::generate(), $workspaceId, $key, $name);
        $this->workflows->add($workflow);

        return $workflow;
    }
}
