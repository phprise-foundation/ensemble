<?php

declare(strict_types=1);

namespace App\Domain\Contract\Team;

use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;

interface TeamRepository
{
    public function add(Team $team): void;

    public function update(Team $team): void;

    public function delete(Team $team): void;

    public function find(WorkspaceId $workspaceId, TeamId $id): ?Team;

    /** @return list<Team> */
    public function all(WorkspaceId $workspaceId): array;
}
