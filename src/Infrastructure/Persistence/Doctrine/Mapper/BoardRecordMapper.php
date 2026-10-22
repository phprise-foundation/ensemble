<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardFilters;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\BoardRecord;

final class BoardRecordMapper
{
    public function toDomain(BoardRecord $record): Board
    {
        return new Board(
            new BoardId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new WorkflowId($record->workflowId()),
            new Slug($record->key()),
            $record->name(),
            new BoardFilters(
                array_values(array_map(static fn(string $id): IssueTypeId => new IssueTypeId($id), $record->filterIssueTypeIds())),
                array_values(array_map(static fn(string $id): StatusId => new StatusId($id), $record->filterStatusIds())),
            ),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(Board $board): BoardRecord
    {
        return new BoardRecord(
            $board->id()->value(),
            $board->workspaceId()->value(),
            $board->workflowId()->value(),
            $board->key()->value(),
            $board->name(),
            array_map(static fn(IssueTypeId $id): string => $id->value(), $board->filters()->issueTypeIds()),
            array_map(static fn(StatusId $id): string => $id->value(), $board->filters()->statusIds()),
            $board->createdAt(),
            $board->updatedAt(),
        );
    }

    public function sync(BoardRecord $record, Board $board): void
    {
        $record->sync(
            $board->name(),
            array_map(static fn(IssueTypeId $id): string => $id->value(), $board->filters()->issueTypeIds()),
            array_map(static fn(StatusId $id): string => $id->value(), $board->filters()->statusIds()),
            $board->updatedAt(),
        );
    }
}
