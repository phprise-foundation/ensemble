<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepository;
use App\Domain\Vcs\GitRepositoryId;
use App\Domain\Vcs\Provider;
use App\Infrastructure\Persistence\Doctrine\Entity\GitRepositoryRecord;

final class GitRepositoryRecordMapper
{
    public function toDomain(GitRepositoryRecord $record): GitRepository
    {
        $secretId = $record->secretId();

        return new GitRepository(
            new GitRepositoryId($record->id()),
            new WorkspaceId($record->workspaceId()),
            $record->name(),
            Provider::fromString($record->provider()),
            $record->url(),
            $record->baseBranch(),
            $secretId === null ? null : new WorkspaceSecretId($secretId),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(GitRepository $repository): GitRepositoryRecord
    {
        return new GitRepositoryRecord(
            $repository->id()->value(),
            $repository->workspaceId()->value(),
            $repository->name(),
            $repository->provider()->value,
            $repository->url()->value(),
            $repository->baseBranch()->value(),
            $repository->secretId()?->value(),
            $repository->createdAt(),
            $repository->updatedAt(),
        );
    }

    public function sync(GitRepositoryRecord $record, GitRepository $repository): void
    {
        $record->sync(
            $repository->name(),
            $repository->url()->value(),
            $repository->baseBranch()->value(),
            $repository->secretId()?->value(),
            $repository->updatedAt(),
        );
    }
}
