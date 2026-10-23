<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateTeam
{
    public function __construct(
        private TeamRepository $teams,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, string $name): Team
    {
        $this->guard->assertManager($actor, $workspaceId);

        $team = new Team(TeamId::generate(), $workspaceId, $name);
        $this->teams->add($team);

        return $team;
    }
}
