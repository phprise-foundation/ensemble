<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkflowRecord;

final class WorkflowRecordMapper
{
    public function toDomain(WorkflowRecord $record): Workflow
    {
        return new Workflow(
            new WorkflowId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new Slug($record->key()),
            $record->name(),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(Workflow $workflow): WorkflowRecord
    {
        return new WorkflowRecord(
            $workflow->id()->value(),
            $workflow->workspaceId()->value(),
            $workflow->key()->value(),
            $workflow->name(),
            $workflow->createdAt(),
            $workflow->updatedAt(),
        );
    }

    public function sync(WorkflowRecord $record, Workflow $workflow): void
    {
        $record->sync($workflow->name(), $workflow->updatedAt());
    }
}
