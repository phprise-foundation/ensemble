<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Team\Role;
use App\Domain\Team\RoleId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\RoleRecord;

final class RoleRecordMapper
{
    public function toDomain(RoleRecord $record): Role
    {
        return new Role(
            new RoleId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new TeamId($record->teamId()),
            $record->name(),
            $record->description(),
            $record->unblock(),
            $record->interactive(),
            $record->createdAt(),
            $record->updatedAt(),
            $record->implement(),
            $record->review(),
            $record->test(),
            $record->merge(),
            $record->deploy(),
        );
    }

    public function newRecord(Role $role): RoleRecord
    {
        return new RoleRecord(
            $role->id()->value(),
            $role->workspaceId()->value(),
            $role->teamId()->value(),
            $role->name(),
            $role->description(),
            $role->unblock(),
            $role->interactive(),
            $role->createdAt(),
            $role->updatedAt(),
            $role->implement(),
            $role->review(),
            $role->test(),
            $role->merge(),
            $role->deploy(),
        );
    }

    public function sync(RoleRecord $record, Role $role): void
    {
        $record->sync(
            $role->name(),
            $role->description(),
            $role->unblock(),
            $role->interactive(),
            $role->updatedAt(),
            $role->implement(),
            $role->review(),
            $role->test(),
            $role->merge(),
            $role->deploy(),
        );
    }
}
