<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Kanban\WorkdirId;
use App\Domain\Tenancy\WorkspaceId;

interface WorkdirPaths
{
    /** Ensures the directory exists on the runner host and returns its path. */
    public function ensure(WorkspaceId $workspaceId, WorkdirId $workdirId): string;

    public function path(WorkspaceId $workspaceId, WorkdirId $workdirId): string;
}
