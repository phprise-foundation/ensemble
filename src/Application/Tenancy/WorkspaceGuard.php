<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Exception\AccessDeniedException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;

final readonly class WorkspaceGuard
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private MembershipRepository $memberships,
    ) {
    }

    public function assertAdmin(User $user): void
    {
        if (!$user->isAdmin()) {
            throw AccessDeniedException::adminOnly();
        }
    }

    public function assertMember(User $user, WorkspaceId $workspaceId): Workspace
    {
        $workspace = $this->workspace($workspaceId);
        if ($user->isAdmin()) {
            return $workspace;
        }

        $this->membership($workspaceId, $user);
        return $workspace;
    }

    public function assertManager(User $user, WorkspaceId $workspaceId): void
    {
        $this->workspace($workspaceId);
        if ($user->isAdmin()) {
            return;
        }

        $membership = $this->membership($workspaceId, $user);
        if (!$membership->isOwner()) {
            throw AccessDeniedException::forWorkspace();
        }
    }

    public function roleOf(User $user, WorkspaceId $workspaceId): ?MembershipRole
    {
        return $this->memberships->find($workspaceId, $user->id())?->role();
    }

    private function workspace(WorkspaceId $workspaceId): Workspace
    {
        return $this->workspaces->find($workspaceId) ?? throw NotFoundException::workspace();
    }

    private function membership(WorkspaceId $workspaceId, User $user): Membership
    {
        return $this->memberships->find($workspaceId, $user->id()) ?? throw NotFoundException::workspace();
    }
}
