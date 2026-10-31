<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\FieldValues;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueRecord;

final class IssueRecordMapper
{
    public function toDomain(IssueRecord $record): Issue
    {
        $workdirId = $record->workdirId();
        $parentKey = $record->parentKey();
        $issue = new Issue(
            new IssueId($record->id()),
            new IssueKey($record->key()),
            new WorkspaceId($record->workspaceId()),
            new IssueTypeId($record->issueTypeId()),
            new StatusId($record->statusId()),
            $record->summary(),
            $record->description(),
            $record->reporter(),
            $record->assigneeId(),
            new FieldValues($record->fieldValues()),
            $record->createdAt(),
            $workdirId === null ? null : new WorkdirId($workdirId),
            $parentKey === null ? null : new IssueKey($parentKey),
            $record->position(),
        );
        $issue->restoreTimestamps($record->updatedAt(), $record->enteredAt());
        $issue->restoreBlocked($record->blocked());

        return $issue;
    }

    public function newRecord(Issue $issue): IssueRecord
    {
        return new IssueRecord(
            $issue->id()->value(),
            $issue->workspaceId()->value(),
            $issue->key()->value(),
            $issue->issueTypeId()->value(),
            $issue->statusId()->value(),
            $issue->summary(),
            $issue->description(),
            $issue->reporter(),
            $issue->assigneeId(),
            $issue->worker(),
            $issue->blocked(),
            $issue->fieldValues()->all(),
            $issue->workdirId()?->value(),
            $issue->parentKey()?->value(),
            $issue->createdAt(),
            $issue->updatedAt(),
            $issue->enteredAt(),
            $issue->position(),
        );
    }

    public function sync(IssueRecord $record, Issue $issue): void
    {
        $record->sync(
            $issue->statusId()->value(),
            $issue->summary(),
            $issue->description(),
            $issue->assigneeId(),
            $issue->worker(),
            $issue->blocked(),
            $issue->fieldValues()->all(),
            $issue->workdirId()?->value(),
            $issue->updatedAt(),
            $issue->enteredAt(),
            $issue->position(),
        );
    }
}
