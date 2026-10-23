<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'agent_roles')]
#[ORM\Index(name: 'idx_agent_roles_role', columns: ['role_id'])]
final class AgentRoleRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'agent_id', type: 'string', length: 32)]
    private string $agentId;

    #[ORM\Id]
    #[ORM\Column(name: 'role_id', type: 'string', length: 32)]
    private string $roleId;

    public function __construct(string $agentId, string $roleId)
    {
        $this->agentId = $agentId;
        $this->roleId = $roleId;
    }

    public function agentId(): string
    {
        return $this->agentId;
    }

    public function roleId(): string
    {
        return $this->roleId;
    }
}
