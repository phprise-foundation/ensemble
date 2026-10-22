<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;

final readonly class UpdateWorkspace
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        ?string $name,
        ?WorkspacePrefix $prefix,
    ): Workspace {
        $this->guard->assertManager($actor, $workspaceId);
        $workspace = $this->workspaces->find($workspaceId) ?? throw NotFoundException::workspace();
        if ($name !== null) {
            $workspace->rename($name);
        }
        if ($prefix !== null) {
            $workspace->changePrefix($prefix);
        }
        $this->workspaces->update($workspace);

        return $workspace;
    }
}
