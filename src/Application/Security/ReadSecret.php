<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Security\WorkspaceSecret;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadSecret
{
    public function __construct(
        private WorkspaceSecretRepository $secrets,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, WorkspaceSecretId $id): WorkspaceSecret
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->secrets->find($workspaceId, $id) ?? throw NotFoundException::secret();
    }
}
