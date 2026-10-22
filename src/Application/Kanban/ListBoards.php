<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Kanban\Board;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListBoards
{
    public function __construct(
        private BoardRepository $boards,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Board> */
    public function execute(User $actor, WorkspaceId $workspaceId): array
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->boards->forWorkspace($workspaceId);
    }
}
