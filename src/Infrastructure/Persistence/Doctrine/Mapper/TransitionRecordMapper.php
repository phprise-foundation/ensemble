<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\TransitionRecord;

final class TransitionRecordMapper
{
    public function toDomain(TransitionRecord $record): Transition
    {
        $outcome = $record->outcome();

        return new Transition(
            new TransitionId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new WorkflowId($record->workflowId()),
            new StatusId($record->fromStatusId()),
            new StatusId($record->toStatusId()),
            TransitionActor::fromString($record->actor()),
            $outcome === null ? null : new Slug($outcome),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(Transition $transition): TransitionRecord
    {
        return new TransitionRecord(
            $transition->id()->value(),
            $transition->workspaceId()->value(),
            $transition->workflowId()->value(),
            $transition->fromStatusId()->value(),
            $transition->toStatusId()->value(),
            $transition->actor()->value,
            $transition->outcome()?->value(),
            $transition->createdAt(),
            $transition->updatedAt(),
        );
    }

    public function sync(TransitionRecord $record, Transition $transition): void
    {
        $record->sync(
            $transition->actor()->value,
            $transition->outcome()?->value(),
            $transition->updatedAt(),
        );
    }
}
