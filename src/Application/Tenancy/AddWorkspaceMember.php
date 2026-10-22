<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class AddWorkspaceMember
{
    public function __construct(
        private WorkspaceGuard $guard,
        private UserRepository $users,
        private MembershipRepository $memberships,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        Login $login,
        MembershipRole $role,
    ): MemberSummary {
        $this->guard->assertManager($actor, $workspaceId);
        $user = $this->users->findByLogin($login) ?? throw NotFoundException::user();
        $membership = $this->memberships->find($workspaceId, $user->id());
        if ($membership === null) {
            $this->memberships->add(new Membership($workspaceId, $user->id(), $role));

            return new MemberSummary($user, $role);
        }

        $this->guardLastOwner($membership, $role);
        $membership->changeRole($role);
        $this->memberships->update($membership);

        return new MemberSummary($user, $role);
    }

    private function guardLastOwner(Membership $membership, MembershipRole $role): void
    {
        if (!$membership->isOwner() || $role === MembershipRole::Owner) {
            return;
        }
        if ($this->memberships->countByRole($membership->workspaceId(), MembershipRole::Owner) <= 1) {
            throw ConflictException::lastOwner();
        }
    }
}
