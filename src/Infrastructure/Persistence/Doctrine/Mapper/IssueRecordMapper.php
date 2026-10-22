<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\FieldValues;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueRecord;

final class IssueRecordMapper
{
    public function toDomain(IssueRecord $record): Issue
    {
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
        );
        $issue->restoreTimestamps($record->updatedAt(), $record->enteredAt());

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
            $issue->createdAt(),
            $issue->updatedAt(),
            $issue->enteredAt(),
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
            $issue->updatedAt(),
            $issue->enteredAt(),
        );
    }
}
