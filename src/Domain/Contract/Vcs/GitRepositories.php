<?php

declare(strict_types=1);

namespace App\Domain\Contract\Vcs;

use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepository;
use App\Domain\Vcs\GitRepositoryId;

interface GitRepositories
{
    public function add(GitRepository $repository): void;

    public function update(GitRepository $repository): void;

    public function delete(GitRepository $repository): void;

    public function find(WorkspaceId $workspaceId, GitRepositoryId $id): ?GitRepository;

    public function findByName(WorkspaceId $workspaceId, string $name): ?GitRepository;

    /** @return list<GitRepository> */
    public function all(WorkspaceId $workspaceId): array;

    public function referenced(WorkspaceSecretId $secretId): bool;
}
