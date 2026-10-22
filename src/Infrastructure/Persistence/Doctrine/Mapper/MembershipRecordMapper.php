<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\MembershipRecord;

final class MembershipRecordMapper
{
    public function toDomain(MembershipRecord $record): Membership
    {
        return new Membership(
            new WorkspaceId($record->workspaceId()),
            new UserId($record->userId()),
            MembershipRole::fromString($record->role()),
            $record->createdAt(),
        );
    }

    public function newRecord(Membership $membership): MembershipRecord
    {
        return new MembershipRecord(
            $membership->workspaceId()->value(),
            $membership->userId()->value(),
            $membership->role()->value,
            $membership->createdAt(),
        );
    }

    public function sync(MembershipRecord $record, Membership $membership): void
    {
        $record->changeRole($membership->role()->value);
    }
}
