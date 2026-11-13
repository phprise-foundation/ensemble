<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final class Transition
{
    private TransitionEndpoint $fromStatusId;

    private TransitionActor $actor;
    private ?Slug $outcome;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly TransitionId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly WorkflowId $workflowId,
        StatusId|TransitionEndpoint $fromStatusId,
        private readonly StatusId $toStatusId,
        TransitionActor $actor,
        ?Slug $outcome = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->fromStatusId = $fromStatusId instanceof StatusId
            ? TransitionEndpoint::status($fromStatusId)
            : $fromStatusId;
        $this->actor = $actor;
        $this->outcome = $outcome;
        $this->updatedAt = $updatedAt;
    }

    public function id(): TransitionId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function workflowId(): WorkflowId
    {
        return $this->workflowId;
    }

    public function fromStatusId(): TransitionEndpoint
    {
        return $this->fromStatusId;
    }

    public function toStatusId(): StatusId
    {
        return $this->toStatusId;
    }

    public function actor(): TransitionActor
    {
        return $this->actor;
    }

    public function outcome(): ?Slug
    {
        return $this->outcome;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function changeActor(TransitionActor $actor): void
    {
        $this->actor = $actor;
    }

    public function changeOutcome(?Slug $outcome): void
    {
        $this->outcome = $outcome;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }
}
