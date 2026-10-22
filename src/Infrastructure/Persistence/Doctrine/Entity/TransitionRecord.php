<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'transitions')]
#[ORM\Index(name: 'idx_transitions_workflow', columns: ['workflow_id'])]
#[ORM\Index(name: 'idx_transitions_from', columns: ['from_status_id'])]
#[ORM\Index(name: 'idx_transitions_to', columns: ['to_status_id'])]
#[ORM\Index(name: 'idx_transitions_workspace', columns: ['workspace_id'])]
final class TransitionRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'workflow_id', type: 'string', length: 32)]
    private string $workflowId;

    #[ORM\Column(name: 'from_status_id', type: 'string', length: 32)]
    private string $fromStatusId;

    #[ORM\Column(name: 'to_status_id', type: 'string', length: 32)]
    private string $toStatusId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $actor;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $outcome;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $workflowId,
        string $fromStatusId,
        string $toStatusId,
        string $actor,
        ?string $outcome,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->workflowId = $workflowId;
        $this->fromStatusId = $fromStatusId;
        $this->toStatusId = $toStatusId;
        $this->actor = $actor;
        $this->outcome = $outcome;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): string { return $this->id; }
    public function workspaceId(): string { return $this->workspaceId; }
    public function workflowId(): string { return $this->workflowId; }
    public function fromStatusId(): string { return $this->fromStatusId; }
    public function toStatusId(): string { return $this->toStatusId; }
    public function actor(): string { return $this->actor; }
    public function outcome(): ?string { return $this->outcome; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function sync(string $actor, ?string $outcome, DateTimeImmutable $updatedAt): void
    {
        $this->actor = $actor;
        $this->outcome = $outcome;
        $this->updatedAt = $updatedAt;
    }
}
