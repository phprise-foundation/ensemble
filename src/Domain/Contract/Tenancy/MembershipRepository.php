<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\WorkspaceId;

interface MembershipRepository
{
    public function add(Membership $membership): void;

    public function update(Membership $membership): void;

    public function remove(WorkspaceId $workspaceId, UserId $userId): void;

    public function find(WorkspaceId $workspaceId, UserId $userId): ?Membership;

    /** @return list<Membership> */
    public function forWorkspace(WorkspaceId $workspaceId): array;

    /** @return list<Membership> */
    public function forUser(UserId $userId): array;

    public function countByRole(WorkspaceId $workspaceId, MembershipRole $role): int;
}
