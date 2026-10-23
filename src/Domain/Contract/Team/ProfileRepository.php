<?php

declare(strict_types=1);

namespace App\Domain\Contract\Team;

use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;

interface ProfileRepository
{
    public function add(Profile $profile): void;

    public function update(Profile $profile): void;

    public function delete(Profile $profile): void;

    public function find(WorkspaceId $workspaceId, ProfileId $id): ?Profile;

    /** @return list<Profile> */
    public function forTeam(WorkspaceId $workspaceId, TeamId $teamId): array;

    public function countForTeam(TeamId $teamId): int;
}
