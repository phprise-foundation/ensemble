<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Kanban\WorkdirId;
use App\Domain\Runner\WorkdirPaths;
use App\Domain\Tenancy\WorkspaceId;
use Override;

final readonly class VarWorkdirPaths implements WorkdirPaths
{
    public function __construct(private string $root)
    {
    }

    #[Override]
    public function ensure(WorkspaceId $workspaceId, WorkdirId $workdirId): string
    {
        $path = $this->path($workspaceId, $workdirId);
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }

    #[Override]
    public function path(WorkspaceId $workspaceId, WorkdirId $workdirId): string
    {
        return rtrim($this->root, '/') . '/' . $workspaceId->value() . '/' . $workdirId->value();
    }
}
