<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateTransition
{
    public function __construct(
        private WorkflowRepository $workflows,
        private StatusRepository $statuses,
        private TransitionRepository $transitions,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
        StatusId $fromStatusId,
        StatusId $toStatusId,
        TransitionActor $transitionActor,
        ?Slug $outcome,
    ): Transition {
        $this->guard->assertManager($actor, $workspaceId);
        $this->workflows->find($workspaceId, $workflowId) ?? throw NotFoundException::workflow();
        $from = $this->statuses->find($workspaceId, $fromStatusId) ?? throw NotFoundException::status();
        $to = $this->statuses->find($workspaceId, $toStatusId) ?? throw NotFoundException::status();
        if ($from->workflowId()->value() !== $workflowId->value() || $to->workflowId()->value() !== $workflowId->value()) {
            throw NotFoundException::status();
        }
        if ($from->issueTypeId()->value() !== $to->issueTypeId()->value()) {
            throw new ValidationException('toStatusId', 'A transition cannot change the issue type.');
        }
        if ($from->id()->value() === $to->id()->value()) {
            throw new ValidationException('toStatusId', 'A transition must target another status.');
        }
        if ($this->transitions->findExisting($workflowId, $fromStatusId, $toStatusId, $transitionActor, $outcome) !== null) {
            throw ConflictException::transitionExists();
        }

        $transition = new Transition(
            TransitionId::generate(),
            $workspaceId,
            $workflowId,
            $fromStatusId,
            $toStatusId,
            $transitionActor,
            $outcome,
        );
        $this->transitions->add($transition);

        return $transition;
    }
}
