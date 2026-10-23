<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Team\EmojiUsage;
use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\ProfileRecord;

final class ProfileRecordMapper
{
    public function toDomain(ProfileRecord $record): Profile
    {
        return new Profile(
            new ProfileId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new TeamId($record->teamId()),
            $record->name(),
            $record->style(),
            $record->tone(),
            EmojiUsage::fromString($record->emojiUsage()),
            $record->advanced(),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(Profile $profile): ProfileRecord
    {
        return new ProfileRecord(
            $profile->id()->value(),
            $profile->workspaceId()->value(),
            $profile->teamId()->value(),
            $profile->name(),
            $profile->style(),
            $profile->tone(),
            $profile->emojiUsage()->value,
            $profile->advanced(),
            $profile->createdAt(),
            $profile->updatedAt(),
        );
    }

    public function sync(ProfileRecord $record, Profile $profile): void
    {
        $record->sync(
            $profile->name(),
            $profile->style(),
            $profile->tone(),
            $profile->emojiUsage()->value,
            $profile->advanced(),
            $profile->updatedAt(),
        );
    }
}
