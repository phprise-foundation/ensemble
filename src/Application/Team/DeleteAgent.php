<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteAgent
{
    public function __construct(
        private AgentRepository $agents,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, AgentId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $agent = $this->agents->find($workspaceId, $id) ?? throw NotFoundException::agent();
        $this->agents->delete($agent);
    }
}
