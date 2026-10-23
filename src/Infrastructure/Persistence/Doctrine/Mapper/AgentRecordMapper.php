<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\AvatarColor;
use App\Domain\Team\LoopInterval;
use App\Domain\Team\MaritalStatus;
use App\Domain\Team\Portrait;
use App\Domain\Team\ProfileId;
use App\Domain\Team\RoleIds;
use App\Domain\Team\RoleId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\AgentRecord;

final class AgentRecordMapper
{
    /**
     * @param RoleIds $roleIds
     */
    public function toDomain(AgentRecord $record, RoleIds $roleIds): Agent
    {
        $profileId = $record->profileId();
        $portrait = $record->portrait();

        $agent = new Agent(
            new AgentId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new TeamId($record->teamId()),
            $record->name(),
            $record->whoAmI(),
            $record->age(),
            MaritalStatus::from($record->maritalStatus()),
            $roleIds,
            $profileId === null ? null : new ProfileId($profileId),
            new AvatarColor($record->avatarColor()),
            new Portrait($portrait ?? ''),
            $record->model(),
            $record->fallbackModel(),
            new LoopInterval($record->loopInterval()),
            $record->active(),
            $record->createdAt(),
            $record->updatedAt(),
        );

        $agent->touch($record->updatedAt());

        return $agent;
    }

    public function newRecord(Agent $agent): AgentRecord
    {
        return new AgentRecord(
            $agent->id()->value(),
            $agent->workspaceId()->value(),
            $agent->teamId()->value(),
            $agent->name(),
            $agent->whoAmI(),
            $agent->age(),
            $agent->maritalStatus()->value,
            $agent->profileId()?->value(),
            $agent->avatarColor()->value(),
            $agent->portrait()->value(),
            $agent->model(),
            $agent->fallbackModel(),
            $agent->interval()->value(),
            $agent->active(),
            $agent->createdAt(),
            $agent->updatedAt(),
        );
    }

    public function sync(AgentRecord $record, Agent $agent): void
    {
        $record->sync(
            $agent->teamId()->value(),
            $agent->name(),
            $agent->whoAmI(),
            $agent->age(),
            $agent->maritalStatus()->value,
            $agent->profileId()?->value(),
            $agent->avatarColor()->value(),
            $agent->portrait()->value(),
            $agent->model(),
            $agent->fallbackModel(),
            $agent->interval()->value(),
            $agent->active(),
            $agent->updatedAt(),
        );
    }
}
