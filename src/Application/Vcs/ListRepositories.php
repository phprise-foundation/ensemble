<?php

declare(strict_types=1);

namespace App\Application\Vcs;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepository;

final readonly class ListRepositories
{
    public function __construct(
        private GitRepositories $repositories,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<GitRepository> */
    public function execute(User $actor, WorkspaceId $workspaceId): array
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->repositories->all($workspaceId);
    }
}
