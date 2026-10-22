<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteColumn
{
    public function __construct(
        private BoardColumnRepository $columns,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, BoardColumnId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $column = $this->columns->find($workspaceId, $id) ?? throw NotFoundException::column();
        $this->columns->delete($column);
    }
}
