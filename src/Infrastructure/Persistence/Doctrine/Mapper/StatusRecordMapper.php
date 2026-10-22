<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\StatusRecord;

final class StatusRecordMapper
{
    public function toDomain(StatusRecord $record): Status
    {
        return new Status(
            new StatusId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new WorkflowId($record->workflowId()),
            new IssueTypeId($record->issueTypeId()),
            new Slug($record->key()),
            $record->name(),
            StatusType::fromString($record->type()),
            $record->color(),
            new Position($record->position()),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(Status $status): StatusRecord
    {
        return new StatusRecord(
            $status->id()->value(),
            $status->workspaceId()->value(),
            $status->workflowId()->value(),
            $status->issueTypeId()->value(),
            $status->key()->value(),
            $status->name(),
            $status->type()->value,
            $status->color(),
            $status->position()->value(),
            $status->createdAt(),
            $status->updatedAt(),
        );
    }

    public function sync(StatusRecord $record, Status $status): void
    {
        $record->sync(
            $status->name(),
            $status->type()->value,
            $status->color(),
            $status->position()->value(),
            $status->updatedAt(),
        );
    }
}
