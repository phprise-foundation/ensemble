<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\RoleId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteRole
{
    public function __construct(
        private RoleRepository $roles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, RoleId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $role = $this->roles->find($workspaceId, $id) ?? throw NotFoundException::role();
        if ($this->roles->assignedToStatus($id)) {
            throw ConflictException::inUse('role');
        }

        $this->roles->delete($role);
    }
}
