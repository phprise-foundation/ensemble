<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardFilters;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateBoard
{
    public function __construct(
        private WorkflowRepository $workflows,
        private BoardRepository $boards,
        private ValidateBoardFilters $validateFilters,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
        Slug $key,
        string $name,
        BoardFilters $filters,
    ): Board {
        $this->guard->assertManager($actor, $workspaceId);
        $this->workflows->find($workspaceId, $workflowId) ?? throw NotFoundException::workflow();
        if ($this->boards->findByKey($workspaceId, $key) !== null) {
            throw ConflictException::duplicateKey();
        }
        $this->validateFilters->execute($workspaceId, $workflowId, $filters);

        $board = new Board(BoardId::generate(), $workspaceId, $workflowId, $key, $name, $filters);
        $this->boards->add($board);

        return $board;
    }
}
