<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ResolveProfile
{
    public function __construct(
        private ProfileRepository $profiles,
    ) {
    }

    public function execute(WorkspaceId $workspaceId, ?ProfileId $profileId): ?Profile
    {
        return $profileId === null ? null : $this->profiles->find($workspaceId, $profileId);
    }
}
