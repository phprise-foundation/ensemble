<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\Workspace;

final readonly class ListWorkspaces
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private MembershipRepository $memberships,
    ) {
    }

    /** @return list<WorkspaceSummary> */
    public function execute(User $user): array
    {
        $workspaces = $user->isAdmin()
            ? $this->workspaces->all()
            : $this->workspaces->forUser($user->id());

        return array_map(
            fn(Workspace $workspace): WorkspaceSummary => new WorkspaceSummary(
                $workspace,
                $this->memberships->find($workspace->id(), $user->id())?->role(),
            ),
            $workspaces,
        );
    }
}
