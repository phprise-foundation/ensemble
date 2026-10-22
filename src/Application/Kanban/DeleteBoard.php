<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\BoardId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteBoard
{
    public function __construct(
        private BoardRepository $boards,
        private BoardColumnRepository $columns,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, BoardId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $board = $this->boards->find($workspaceId, $id) ?? throw NotFoundException::board();
        if ($this->columns->countForBoard($id) > 0) {
            throw ConflictException::inUse('board');
        }

        $this->boards->delete($board);
    }
}
