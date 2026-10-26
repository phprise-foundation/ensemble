<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateColumn
{
    public function __construct(
        private BoardRepository $boards,
        private BoardColumnRepository $columns,
        private ValidateColumnStatuses $validateStatuses,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        BoardId $boardId,
        string $name,
        ?Position $position,
        ?int $wip,
        StatusIds $statusIds,
    ): BoardColumn {
        $this->guard->assertManager($actor, $workspaceId);
        $board = $this->boards->find($workspaceId, $boardId) ?? throw NotFoundException::board();
        $this->validateStatuses->execute($workspaceId, $board->workflowId(), $statusIds);
        $this->guardUniqueStatuses($workspaceId, $boardId, $statusIds, null);
        $position ??= $this->nextPosition($workspaceId, $boardId);
        $this->guardUniquePosition($workspaceId, $boardId, $position, null);

        $column = new BoardColumn(
            BoardColumnId::generate(),
            $workspaceId,
            $boardId,
            $name,
            $position,
            $wip,
            $statusIds,
        );
        $this->columns->add($column);

        return $column;
    }

    private function nextPosition(WorkspaceId $workspaceId, BoardId $boardId): Position
    {
        $max = -1;
        foreach ($this->columns->forBoard($workspaceId, $boardId) as $column) {
            $max = max($max, $column->position()->value());
        }

        return new Position($max + 1);
    }

    private function guardUniquePosition(
        WorkspaceId $workspaceId,
        BoardId $boardId,
        Position $position,
        ?BoardColumnId $except,
    ): void {
        foreach ($this->columns->forBoard($workspaceId, $boardId) as $column) {
            if ($except !== null && $column->id()->value() === $except->value()) {
                continue;
            }
            if ($column->position()->value() === $position->value()) {
                throw new ValidationException('position', 'Another column already uses this position.');
            }
        }
    }

    private function guardUniqueStatuses(
        WorkspaceId $workspaceId,
        BoardId $boardId,
        StatusIds $statusIds,
        ?BoardColumnId $except,
    ): void {
        $wanted = array_map(static fn(StatusId $id): string => $id->value(), $statusIds->all());
        foreach ($this->columns->forBoard($workspaceId, $boardId) as $column) {
            if ($except !== null && $column->id()->value() === $except->value()) {
                continue;
            }
            foreach ($column->statusIds()->all() as $existing) {
                if (in_array($existing->value(), $wanted, true)) {
                    throw new ValidationException('statusIds', 'A status can belong to only one column per board.');
                }
            }
        }
    }
}
