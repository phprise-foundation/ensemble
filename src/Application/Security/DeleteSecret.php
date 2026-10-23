<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteSecret
{
    public function __construct(
        private WorkspaceSecretRepository $secrets,
        private GitRepositories $repositories,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, WorkspaceSecretId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $secret = $this->secrets->find($workspaceId, $id) ?? throw NotFoundException::secret();
        if ($this->repositories->referenced($id)) {
            throw ConflictException::inUse('secret');
        }

        $this->secrets->delete($secret);
    }
}
