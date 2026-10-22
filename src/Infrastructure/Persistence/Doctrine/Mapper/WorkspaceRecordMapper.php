<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkspaceRecord;

final class WorkspaceRecordMapper
{
    public function toDomain(WorkspaceRecord $record): Workspace
    {
        return new Workspace(
            new WorkspaceId($record->id()),
            $record->name(),
            new WorkspacePrefix($record->prefix()),
            $record->createdAt(),
        );
    }

    public function newRecord(Workspace $workspace): WorkspaceRecord
    {
        return new WorkspaceRecord(
            $workspace->id()->value(),
            $workspace->name(),
            $workspace->prefix()->value(),
            $workspace->createdAt(),
        );
    }

    public function sync(WorkspaceRecord $record, Workspace $workspace): void
    {
        $record->rename($workspace->name());
        $record->changePrefix($workspace->prefix()->value());
    }
}
