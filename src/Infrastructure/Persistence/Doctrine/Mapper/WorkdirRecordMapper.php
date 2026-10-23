<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Kanban\WorkdirKind;
use App\Domain\Kanban\WorkdirState;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepositoryId;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkdirRecord;

final class WorkdirRecordMapper
{
    public function toDomain(WorkdirRecord $record): Workdir
    {
        $repositoryId = $record->repositoryId();

        return new Workdir(
            new WorkdirId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new IssueId($record->ownerIssueId()),
            WorkdirKind::from($record->kind()),
            $repositoryId === null ? null : new GitRepositoryId($repositoryId),
            $record->branch(),
            WorkdirState::from($record->state()),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(Workdir $workdir): WorkdirRecord
    {
        return new WorkdirRecord(
            $workdir->id()->value(),
            $workdir->workspaceId()->value(),
            $workdir->ownerIssueId()->value(),
            $workdir->kind()->value,
            $workdir->state()->value,
            $workdir->repositoryId()?->value(),
            $workdir->branch()?->value(),
            $workdir->createdAt(),
            $workdir->updatedAt(),
        );
    }

    public function sync(WorkdirRecord $record, Workdir $workdir): void
    {
        $record->sync(
            $workdir->kind()->value,
            $workdir->state()->value,
            $workdir->repositoryId()?->value(),
            $workdir->branch()?->value(),
            $workdir->updatedAt(),
        );
    }
}
