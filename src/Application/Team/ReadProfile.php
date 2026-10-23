<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadProfile
{
    public function __construct(
        private ProfileRepository $profiles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, ProfileId $id): Profile
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->profiles->find($workspaceId, $id) ?? throw NotFoundException::profile();
    }
}
