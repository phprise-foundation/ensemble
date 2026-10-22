<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use DateTimeImmutable;

final class Membership
{
    public function __construct(
        private readonly WorkspaceId $workspaceId,
        private readonly UserId $userId,
        private MembershipRole $role,
        private readonly ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function role(): MembershipRole
    {
        return $this->role;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function changeRole(MembershipRole $role): void
    {
        $this->role = $role;
    }

    public function isOwner(): bool
    {
        return $this->role === MembershipRole::Owner;
    }
}
