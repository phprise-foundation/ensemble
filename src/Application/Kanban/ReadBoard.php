<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadBoard
{
    public function __construct(
        private BoardRepository $boards,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, BoardId $id): Board
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->boards->find($workspaceId, $id) ?? throw NotFoundException::board();
    }
}
