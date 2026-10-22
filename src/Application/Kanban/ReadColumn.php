<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadColumn
{
    public function __construct(
        private BoardColumnRepository $columns,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, BoardColumnId $id): BoardColumn
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->columns->find($workspaceId, $id) ?? throw NotFoundException::column();
    }
}
