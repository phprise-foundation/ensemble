<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workspace_members')]
#[ORM\Index(name: 'idx_workspace_members_user', columns: ['user_id'])]
final class MembershipRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: 'string', length: 32)]
    private string $userId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $role;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $workspaceId,
        string $userId,
        string $role,
        DateTimeImmutable $createdAt,
    ) {
        $this->workspaceId = $workspaceId;
        $this->userId = $userId;
        $this->role = $role;
        $this->createdAt = $createdAt;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changeRole(string $role): void
    {
        $this->role = $role;
    }
}
