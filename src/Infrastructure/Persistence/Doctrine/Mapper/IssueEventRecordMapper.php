<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueEventType;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\StatusId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueEventRecord;

final class IssueEventRecordMapper
{
    public function toDomain(IssueEventRecord $record): IssueEvent
    {
        $from = $record->fromStatusId();

        return new IssueEvent(
            new IssueEventId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new IssueId($record->issueId()),
            $this->type($record),
            $from === null ? null : new StatusId($from),
            $record->toStatusId() === null ? null : new StatusId($record->toStatusId()),
            $record->actor(),
            $record->summary(),
            $record->createdAt(),
        );
    }

    public function newRecord(IssueEvent $event): IssueEventRecord
    {
        return new IssueEventRecord(
            $event->id()->value(),
            $event->workspaceId()->value(),
            $event->issueId()->value(),
            $event->type()->value,
            $event->fromStatusId()?->value(),
            $event->toStatusId()?->value(),
            $event->actor(),
            $event->summary(),
            $event->createdAt(),
        );
    }

    private function type(IssueEventRecord $record): IssueEventType
    {
        $type = IssueEventType::fromString($record->type());

        return $type === IssueEventType::Status && $record->fromStatusId() === null
            ? IssueEventType::Created
            : $type;
    }
}
