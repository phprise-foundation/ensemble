<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Team\Agent;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListAgents
{
    public function __construct(
        private AgentRepository $agents,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Agent> */
    public function execute(User $actor, WorkspaceId $workspaceId, TeamId $teamId): array
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->agents->forTeam($workspaceId, $teamId);
    }
}
