<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Contract\Tenancy\UnitOfWork;
use App\Domain\Contract\Tenancy\WorkspaceDefaults;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;

final readonly class CreateWorkspace
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private MembershipRepository $memberships,
        private WorkspaceDefaults $defaults,
        private UnitOfWork $unitOfWork,
    ) {
    }

    public function execute(User $owner, string $name, WorkspacePrefix $prefix): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = $this->unitOfWork->run(function () use ($owner, $name, $prefix): Workspace {
            $workspace = new Workspace(WorkspaceId::generate(), $name, $prefix);
            $this->workspaces->add($workspace);
            $this->memberships->add(new Membership($workspace->id(), $owner->id(), MembershipRole::Owner));
            $this->defaults->seed($workspace->id());

            return $workspace;
        });

        return $workspace;
    }
}
