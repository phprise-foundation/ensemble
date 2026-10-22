<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Slug;
use App\Domain\Tenancy\WorkspaceId;

interface BoardRepository
{
    public function add(Board $board): void;

    public function update(Board $board): void;

    public function delete(Board $board): void;

    public function find(WorkspaceId $workspaceId, BoardId $id): ?Board;

    public function findByKey(WorkspaceId $workspaceId, Slug $key): ?Board;

    /** @return list<Board> */
    public function forWorkspace(WorkspaceId $workspaceId): array;
}
