<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Level;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueTypeRecord;

final class IssueTypeRecordMapper
{
    public function toDomain(IssueTypeRecord $record): IssueType
    {
        return new IssueType(
            new IssueTypeId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new WorkflowId($record->workflowId()),
            new Slug($record->key()),
            $record->name(),
            new Level($record->level()),
            new Position($record->position()),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(IssueType $issueType): IssueTypeRecord
    {
        return new IssueTypeRecord(
            $issueType->id()->value(),
            $issueType->workspaceId()->value(),
            $issueType->workflowId()->value(),
            $issueType->key()->value(),
            $issueType->name(),
            $issueType->level()->value(),
            $issueType->position()->value(),
            $issueType->createdAt(),
            $issueType->updatedAt(),
        );
    }

    public function sync(IssueTypeRecord $record, IssueType $issueType): void
    {
        $record->sync(
            $issueType->name(),
            $issueType->level()->value(),
            $issueType->position()->value(),
            $issueType->updatedAt(),
        );
    }
}
