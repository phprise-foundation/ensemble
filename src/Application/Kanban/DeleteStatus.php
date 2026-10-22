<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteStatus
{
    public function __construct(
        private StatusRepository $statuses,
        private TransitionRepository $transitions,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, StatusId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $status = $this->statuses->find($workspaceId, $id) ?? throw NotFoundException::status();
        if ($this->transitions->countForStatus($id) > 0) {
            throw ConflictException::inUse('status');
        }
        if ($this->statuses->countForIssueType($status->issueTypeId()) <= 1) {
            throw ConflictException::inUse('status');
        }
        if ($status->isInitial() && $this->statuses->countByType($status->issueTypeId(), StatusType::Initial) <= 1) {
            throw ConflictException::initialStatus();
        }

        $this->statuses->delete($status);
    }
}
