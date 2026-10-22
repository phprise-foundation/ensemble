<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListWorkspaceMembers
{
    public function __construct(
        private WorkspaceGuard $guard,
        private MembershipRepository $memberships,
        private UserRepository $users,
    ) {
    }

    /** @return list<MemberSummary> */
    public function execute(User $actor, WorkspaceId $workspaceId): array
    {
        $this->guard->assertMember($actor, $workspaceId);

        return array_map(
            fn(Membership $membership): MemberSummary => new MemberSummary(
                $this->users->find($membership->userId()) ?? throw NotFoundException::user(),
                $membership->role(),
            ),
            $this->memberships->forWorkspace($workspaceId),
        );
    }
}
