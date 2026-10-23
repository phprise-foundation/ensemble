<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Security\WorkspaceSecret;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkspaceSecretRecord;

final class WorkspaceSecretRecordMapper
{
    public function toDomain(WorkspaceSecretRecord $record): WorkspaceSecret
    {
        return new WorkspaceSecret(
            new WorkspaceSecretId($record->id()),
            new WorkspaceId($record->workspaceId()),
            $record->name(),
            $record->ciphertext(),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(WorkspaceSecret $secret): WorkspaceSecretRecord
    {
        return new WorkspaceSecretRecord(
            $secret->id()->value(),
            $secret->workspaceId()->value(),
            $secret->name()->value(),
            $secret->ciphertext()->value(),
            $secret->createdAt(),
            $secret->updatedAt(),
        );
    }

    public function sync(WorkspaceSecretRecord $record, WorkspaceSecret $secret): void
    {
        $record->sync(
            $secret->name()->value(),
            $secret->ciphertext()->value(),
            $secret->updatedAt(),
        );
    }
}
