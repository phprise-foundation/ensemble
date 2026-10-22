<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\TransitionId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateTransition
{
    public function __construct(
        private TransitionRepository $transitions,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        TransitionId $id,
        ?TransitionActor $transitionActor,
        bool $outcomeProvided,
        ?Slug $outcome,
    ): Transition {
        $this->guard->assertManager($actor, $workspaceId);
        $transition = $this->transitions->find($workspaceId, $id) ?? throw NotFoundException::transition();
        $nextActor = $transitionActor ?? $transition->actor();
        $nextOutcome = $outcomeProvided ? $outcome : $transition->outcome();
        $existing = $this->transitions->findExisting(
            $transition->workflowId(),
            $transition->fromStatusId(),
            $transition->toStatusId(),
            $nextActor,
            $nextOutcome,
        );
        if ($existing !== null && $existing->id()->value() !== $id->value()) {
            throw ConflictException::transitionExists();
        }
        $transition->changeActor($nextActor);
        $transition->changeOutcome($nextOutcome);
        $transition->touch(new DateTimeImmutable());
        $this->transitions->update($transition);

        return $transition;
    }
}
