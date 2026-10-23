<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\Profile;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListProfiles
{
    public function __construct(
        private TeamRepository $teams,
        private ProfileRepository $profiles,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Profile> */
    public function execute(User $actor, WorkspaceId $workspaceId, TeamId $teamId): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $this->teams->find($workspaceId, $teamId) ?? throw NotFoundException::team();

        return $this->profiles->forTeam($workspaceId, $teamId);
    }
}
