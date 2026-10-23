<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Team\Team;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListTeams
{
    public function __construct(
        private TeamRepository $teams,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Team> */
    public function execute(User $actor, WorkspaceId $workspaceId): array
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->teams->all($workspaceId);
    }
}
