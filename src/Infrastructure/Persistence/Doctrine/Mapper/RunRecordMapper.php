<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\IssueId;
use App\Domain\Runner\Run;
use App\Domain\Runner\RunId;
use App\Domain\Runner\RunState;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\RunRecord;

final class RunRecordMapper
{
    public function toDomain(RunRecord $record): Run
    {
        return new Run(
            new RunId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new AgentId($record->agentId()),
            new IssueId($record->issueId()),
            RunState::from($record->state()),
            $record->reason(),
            $record->attempt(),
            $record->leaseExpiresAt(),
            $record->startedAt(),
            $record->prompt(),
            $record->createdAt(),
            $record->message(),
            $record->finishedAt(),
            $record->pullRequestUrl(),
        );
    }

    public function newRecord(Run $run): RunRecord
    {
        return new RunRecord(
            $run->id()->value(),
            $run->workspaceId()->value(),
            $run->agentId()->value(),
            $run->issueId()->value(),
            $run->state()->value,
            $run->reason(),
            $run->prompt(),
            $run->message(),
            $run->attempt(),
            $run->leaseExpiresAt(),
            $run->startedAt(),
            $run->finishedAt(),
            $run->createdAt(),
            $run->pullRequestUrl(),
        );
    }

    public function sync(RunRecord $record, Run $run): void
    {
        $record->sync(
            $run->state()->value,
            $run->reason(),
            $run->prompt(),
            $run->message(),
            $run->attempt(),
            $run->leaseExpiresAt(),
            $run->startedAt(),
            $run->finishedAt(),
            $run->pullRequestUrl(),
        );
    }
}
