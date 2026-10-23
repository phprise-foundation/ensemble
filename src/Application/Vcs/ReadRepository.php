<?php

declare(strict_types=1);

namespace App\Application\Vcs;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Exception\NotFoundException;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepository;
use App\Domain\Vcs\GitRepositoryId;

final readonly class ReadRepository
{
    public function __construct(
        private GitRepositories $repositories,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, GitRepositoryId $id): GitRepository
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->repositories->find($workspaceId, $id) ?? throw NotFoundException::repository();
    }
}
