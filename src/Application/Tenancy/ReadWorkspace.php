<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Tenancy\User;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadWorkspace
{
    public function __construct(private WorkspaceGuard $guard)
    {
    }

    public function execute(User $actor, WorkspaceId $workspaceId): Workspace
    {
        return $this->guard->assertMember($actor, $workspaceId);
    }
}
