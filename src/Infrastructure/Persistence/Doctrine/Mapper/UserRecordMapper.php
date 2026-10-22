<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Security\PasswordHash;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;
use App\Infrastructure\Persistence\Doctrine\Entity\UserRecord;

final class UserRecordMapper
{
    public function toDomain(UserRecord $record): User
    {
        return new User(
            new UserId($record->id()),
            new Login($record->login()),
            new PasswordHash($record->passwordHash()),
            $record->displayName(),
            $record->avatar(),
            $record->avatarColor(),
            $record->isAdmin(),
            $record->createdAt(),
        );
    }

    public function newRecord(User $user): UserRecord
    {
        return new UserRecord(
            $user->id()->value(),
            $user->login()->value(),
            $user->passwordHash()->value(),
            $user->displayName(),
            $user->avatar(),
            $user->avatarColor(),
            $user->isAdmin(),
            $user->createdAt(),
        );
    }

    public function sync(UserRecord $record, User $user): void
    {
        $record->sync(
            $user->passwordHash()->value(),
            $user->displayName(),
            $user->avatar(),
            $user->avatarColor(),
            $user->isAdmin(),
        );
    }
}
