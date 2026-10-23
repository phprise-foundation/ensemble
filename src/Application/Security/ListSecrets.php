<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Security\WorkspaceSecret;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListSecrets
{
    public function __construct(
        private WorkspaceSecretRepository $secrets,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<WorkspaceSecret> */
    public function execute(User $actor, WorkspaceId $workspaceId): array
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->secrets->all($workspaceId);
    }
}
