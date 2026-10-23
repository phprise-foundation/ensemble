<?php

declare(strict_types=1);

namespace App\Domain\Contract\Team;

use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;

interface AgentRepository
{
    public function add(Agent $agent): void;

    public function update(Agent $agent): void;

    public function delete(Agent $agent): void;

    public function find(WorkspaceId $workspaceId, AgentId $id): ?Agent;

    /** @return list<Agent> */
    public function forTeam(WorkspaceId $workspaceId, TeamId $teamId): array;

    /** @return list<Agent> */
    public function forWorkspace(WorkspaceId $workspaceId): array;
}
