<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteTeam
{
    public function __construct(
        private TeamRepository $teams,
        private RoleRepository $roles,
        private ProfileRepository $profiles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, TeamId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $team = $this->teams->find($workspaceId, $id) ?? throw NotFoundException::team();
        if ($this->roles->countForTeam($id) > 0 || $this->profiles->countForTeam($id) > 0) {
            throw ConflictException::inUse('team');
        }

        $this->teams->delete($team);
    }
}
