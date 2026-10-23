<?php

declare(strict_types=1);

namespace App\Domain\Contract\Team;

use App\Domain\Team\Role;
use App\Domain\Team\RoleId;
use App\Domain\Team\RoleIds;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;

interface RoleRepository
{
    public function add(Role $role): void;

    public function update(Role $role): void;

    public function delete(Role $role): void;

    public function find(WorkspaceId $workspaceId, RoleId $id): ?Role;

    /** @return list<Role> */
    public function forTeam(WorkspaceId $workspaceId, TeamId $teamId): array;

    public function findMany(WorkspaceId $workspaceId, RoleIds $ids): RoleIds;

    public function countForTeam(TeamId $teamId): int;

    public function assignedToStatus(RoleId $id): bool;
}
