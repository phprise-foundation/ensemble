<?php

declare(strict_types=1);

namespace App\Domain\Contract\Security;

use App\Domain\Security\WorkspaceSecret;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;

interface WorkspaceSecretRepository
{
    public function add(WorkspaceSecret $secret): void;

    public function update(WorkspaceSecret $secret): void;

    public function delete(WorkspaceSecret $secret): void;

    public function find(WorkspaceId $workspaceId, WorkspaceSecretId $id): ?WorkspaceSecret;

    public function findByName(WorkspaceId $workspaceId, string $name): ?WorkspaceSecret;

    /** @return list<WorkspaceSecret> */
    public function all(WorkspaceId $workspaceId): array;
}
