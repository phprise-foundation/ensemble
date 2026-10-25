<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class IssueEvent
{
    public function __construct(
        private IssueEventId $id,
        private WorkspaceId $workspaceId,
        private IssueId $issueId,
        private IssueEventType $type,
        private ?StatusId $fromStatusId,
        private ?StatusId $toStatusId,
        private string $actor,
        private ?string $summary = null,
        private ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function id(): IssueEventId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function issueId(): IssueId
    {
        return $this->issueId;
    }

    public function type(): IssueEventType
    {
        return $this->type;
    }

    public function fromStatusId(): ?StatusId
    {
        return $this->fromStatusId;
    }

    public function toStatusId(): ?StatusId
    {
        return $this->toStatusId;
    }

    public function actor(): string
    {
        return $this->actor;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }
}
