<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

use App\Domain\Tenancy\WorkspaceId;

interface WorkspaceDefaults
{
    public function seed(WorkspaceId $workspaceId): void;
}
