<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;

interface IssueKeyGenerator
{
    public function next(WorkspaceId $workspaceId, WorkspacePrefix $prefix): IssueKey;
}
