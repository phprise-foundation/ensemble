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
use App\Domain\Kanban\Position;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateColumn
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
        BoardColumnId $id,
        ?string $name,
        ?Position $position,
        bool $wipProvided,
        ?int $wip,
        bool $statusesProvided,
        StatusIds $statusIds,
    ): BoardColumn {
        $this->guard->assertManager($actor, $workspaceId);
        $column = $this->columns->find($workspaceId, $id) ?? throw NotFoundException::column();
        $board = $this->boards->find($workspaceId, $column->boardId()) ?? throw NotFoundException::board();
        if ($name !== null) {
            $column->rename($name);
        }
        if ($position !== null) {
            $column->placeAt($position);
        }
        if ($wipProvided) {
            $column->changeWip($wip);
        }
        if ($statusesProvided) {
            $this->validateStatuses->execute($workspaceId, $board->workflowId(), $statusIds);
            $this->guardUniqueStatuses($workspaceId, $column, $statusIds);
            $column->changeStatuses($statusIds);
        }
        $column->touch(new DateTimeImmutable());
        $this->columns->update($column);

        return $column;
    }

    private function guardUniqueStatuses(WorkspaceId $workspaceId, BoardColumn $column, StatusIds $statusIds): void
    {
        $wanted = array_map(static fn(StatusId $id): string => $id->value(), $statusIds->all());
        foreach ($this->columns->forBoard($workspaceId, $column->boardId()) as $other) {
            if ($other->id()->value() === $column->id()->value()) {
                continue;
            }
            foreach ($other->statusIds()->all() as $existing) {
                if (in_array($existing->value(), $wanted, true)) {
                    throw new ValidationException('statusIds', 'A status can belong to only one column per board.');
                }
            }
        }
    }
}
