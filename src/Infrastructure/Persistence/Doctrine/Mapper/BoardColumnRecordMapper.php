<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\BoardColumnRecord;

final class BoardColumnRecordMapper
{
    public function toDomain(BoardColumnRecord $record): BoardColumn
    {
        return new BoardColumn(
            new BoardColumnId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new BoardId($record->boardId()),
            $record->name(),
            new Position($record->position()),
            $record->wip(),
            new StatusIds(array_values(array_map(static fn(string $id): StatusId => new StatusId($id), $record->statusIds()))),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(BoardColumn $column): BoardColumnRecord
    {
        return new BoardColumnRecord(
            $column->id()->value(),
            $column->workspaceId()->value(),
            $column->boardId()->value(),
            $column->name(),
            $column->position()->value(),
            $column->wip(),
            array_map(static fn(StatusId $id): string => $id->value(), $column->statusIds()->all()),
            $column->createdAt(),
            $column->updatedAt(),
        );
    }

    public function sync(BoardColumnRecord $record, BoardColumn $column): void
    {
        $record->sync(
            $column->name(),
            $column->position()->value(),
            $column->wip(),
            array_map(static fn(StatusId $id): string => $id->value(), $column->statusIds()->all()),
            $column->updatedAt(),
        );
    }
}
