<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateWorkflow
{
    public function __construct(
        private WorkflowRepository $workflows,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, WorkflowId $id, ?string $name): Workflow
    {
        $this->guard->assertManager($actor, $workspaceId);
        $workflow = $this->workflows->find($workspaceId, $id) ?? throw NotFoundException::workflow();
        if ($name !== null) {
            $workflow->rename($name);
        }
        $workflow->touch(new DateTimeImmutable());
        $this->workflows->update($workflow);

        return $workflow;
    }
}
