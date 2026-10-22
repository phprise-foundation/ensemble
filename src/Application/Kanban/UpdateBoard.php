<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardFilters;
use App\Domain\Kanban\BoardId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateBoard
{
    public function __construct(
        private BoardRepository $boards,
        private ValidateBoardFilters $validateFilters,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        BoardId $id,
        ?string $name,
        bool $filtersProvided,
        BoardFilters $filters,
    ): Board {
        $this->guard->assertManager($actor, $workspaceId);
        $board = $this->boards->find($workspaceId, $id) ?? throw NotFoundException::board();
        if ($name !== null) {
            $board->rename($name);
        }
        if ($filtersProvided) {
            $this->validateFilters->execute($workspaceId, $board->workflowId(), $filters);
            $board->changeFilters($filters);
        }
        $board->touch(new DateTimeImmutable());
        $this->boards->update($board);

        return $board;
    }
}
