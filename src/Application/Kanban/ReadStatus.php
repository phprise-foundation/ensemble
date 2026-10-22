<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadStatus
{
    public function __construct(
        private StatusRepository $statuses,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, StatusId $id): Status
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->statuses->find($workspaceId, $id) ?? throw NotFoundException::status();
    }
}
