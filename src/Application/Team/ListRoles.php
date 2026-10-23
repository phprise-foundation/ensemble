<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\Role;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListRoles
{
    public function __construct(
        private TeamRepository $teams,
        private RoleRepository $roles,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Role> */
    public function execute(User $actor, WorkspaceId $workspaceId, TeamId $teamId): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $this->teams->find($workspaceId, $teamId) ?? throw NotFoundException::team();

        return $this->roles->forTeam($workspaceId, $teamId);
    }
}
