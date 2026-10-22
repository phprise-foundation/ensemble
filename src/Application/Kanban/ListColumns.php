<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListColumns
{
    public function __construct(
        private BoardRepository $boards,
        private BoardColumnRepository $columns,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<BoardColumn> */
    public function execute(User $actor, WorkspaceId $workspaceId, BoardId $boardId): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $this->boards->find($workspaceId, $boardId) ?? throw NotFoundException::board();

        return $this->columns->forBoard($workspaceId, $boardId);
    }
}
