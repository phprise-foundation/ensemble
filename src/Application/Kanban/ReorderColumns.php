<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Position;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class ReorderColumns
{
    public function __construct(
        private BoardRepository $boards,
        private BoardColumnRepository $columns,
        private WorkspaceGuard $guard,
    ) {
    }

    /**
     * @param list<string> $columnIds
     * @return list<BoardColumn>
     */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        BoardId $boardId,
        array $columnIds,
    ): array {
        $this->guard->assertManager($actor, $workspaceId);
        $this->boards->find($workspaceId, $boardId) ?? throw NotFoundException::board();

        $byId = [];
        foreach ($this->columns->forBoard($workspaceId, $boardId) as $column) {
            $byId[$column->id()->value()] = $column;
        }
        if (count($columnIds) !== count($byId) || count(array_unique($columnIds)) !== count($columnIds)) {
            throw new ValidationException('columnIds', 'Send every column of the board exactly once.');
        }

        $ordered = [];
        foreach ($columnIds as $index => $id) {
            $column = $byId[$id] ?? throw new ValidationException('columnIds', 'Unknown column for this board.');
            $column->placeAt(new Position($index));
            $column->touch(new DateTimeImmutable());
            $ordered[] = $column;
        }
        foreach ($ordered as $column) {
            $this->columns->update($column);
        }

        return $ordered;
    }
}
