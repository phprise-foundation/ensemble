<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Security\Session;
use App\Domain\Security\SessionTokenHash;
use App\Domain\Tenancy\UserId;
use App\Infrastructure\Persistence\Doctrine\Entity\SessionRecord;

final class SessionRecordMapper
{
    public function toDomain(SessionRecord $record): Session
    {
        return new Session(
            new SessionTokenHash($record->tokenHash()),
            new UserId($record->userId()),
            $record->expiresAt(),
            $record->createdAt(),
        );
    }

    public function newRecord(Session $session): SessionRecord
    {
        return new SessionRecord(
            $session->tokenHash()->value(),
            $session->userId()->value(),
            $session->expiresAt(),
            $session->createdAt(),
        );
    }
}
