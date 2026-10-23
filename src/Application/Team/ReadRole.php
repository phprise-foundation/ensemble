<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\Role;
use App\Domain\Team\RoleId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadRole
{
    public function __construct(
        private RoleRepository $roles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, RoleId $id): Role
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->roles->find($workspaceId, $id) ?? throw NotFoundException::role();
    }
}
