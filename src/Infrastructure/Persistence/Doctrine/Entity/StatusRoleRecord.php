<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'status_roles')]
#[ORM\Index(name: 'idx_status_roles_role', columns: ['role_id'])]
final class StatusRoleRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'status_id', type: 'string', length: 32)]
    private string $statusId;

    #[ORM\Id]
    #[ORM\Column(name: 'role_id', type: 'string', length: 32)]
    private string $roleId;

    public function __construct(string $statusId, string $roleId)
    {
        $this->statusId = $statusId;
        $this->roleId = $roleId;
    }

    public function statusId(): string
    {
        return $this->statusId;
    }

    public function roleId(): string
    {
        return $this->roleId;
    }
}
