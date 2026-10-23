<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\TeamRecord;

final class TeamRecordMapper
{
    public function toDomain(TeamRecord $record): Team
    {
        return new Team(
            new TeamId($record->id()),
            new WorkspaceId($record->workspaceId()),
            $record->name(),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(Team $team): TeamRecord
    {
        return new TeamRecord(
            $team->id()->value(),
            $team->workspaceId()->value(),
            $team->name(),
            $team->createdAt(),
            $team->updatedAt(),
        );
    }

    public function sync(TeamRecord $record, Team $team): void
    {
        $record->sync($team->name(), $team->updatedAt());
    }
}
