<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Kanban\BoardId;
use App\Domain\Tenancy\WorkspaceId;

interface BoardColumnRepository
{
    public function add(BoardColumn $column): void;

    public function update(BoardColumn $column): void;

    public function delete(BoardColumn $column): void;

    public function find(WorkspaceId $workspaceId, BoardColumnId $id): ?BoardColumn;

    /** @return list<BoardColumn> */
    public function forBoard(WorkspaceId $workspaceId, BoardId $boardId): array;

    public function countForBoard(BoardId $boardId): int;
}
