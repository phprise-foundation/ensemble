<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;

interface WorkspaceRepository
{
    public function add(Workspace $workspace): void;

    public function update(Workspace $workspace): void;

    public function find(WorkspaceId $id): ?Workspace;

    /** @return list<Workspace> */
    public function all(): array;

    /** @return list<Workspace> */
    public function forUser(UserId $userId): array;

    public function existsAny(): bool;
}
