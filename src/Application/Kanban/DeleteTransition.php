<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\TransitionId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteTransition
{
    public function __construct(
        private TransitionRepository $transitions,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, TransitionId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $transition = $this->transitions->find($workspaceId, $id) ?? throw NotFoundException::transition();
        $this->transitions->delete($transition);
    }
}
