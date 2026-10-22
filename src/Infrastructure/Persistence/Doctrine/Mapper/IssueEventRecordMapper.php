<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventId;
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
            $from === null ? null : new StatusId($from),
            new StatusId($record->toStatusId()),
            $record->actor(),
            $record->createdAt(),
        );
    }

    public function newRecord(IssueEvent $event): IssueEventRecord
    {
        return new IssueEventRecord(
            $event->id()->value(),
            $event->workspaceId()->value(),
            $event->issueId()->value(),
            $event->fromStatusId()?->value(),
            $event->toStatusId()->value(),
            $event->actor(),
            $event->createdAt(),
        );
    }
}
