<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\WorkspaceId;

final readonly class RemoveWorkspaceMember
{
    public function __construct(
        private WorkspaceGuard $guard,
        private MembershipRepository $memberships,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, UserId $userId): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $membership = $this->memberships->find($workspaceId, $userId) ?? throw NotFoundException::membership();
        if ($membership->isOwner() && $this->memberships->countByRole($workspaceId, MembershipRole::Owner) <= 1) {
            throw ConflictException::lastOwner();
        }

        $this->memberships->remove($workspaceId, $userId);
    }
}
