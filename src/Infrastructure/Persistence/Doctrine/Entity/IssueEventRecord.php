<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'issue_events')]
#[ORM\Index(name: 'idx_issue_events_issue', columns: ['issue_id'])]
#[ORM\Index(name: 'idx_issue_events_workspace', columns: ['workspace_id'])]
final class IssueEventRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'issue_id', type: 'string', length: 32)]
    private string $issueId;

    #[ORM\Column(name: 'from_status_id', type: 'string', length: 32, nullable: true)]
    private ?string $fromStatusId;

    #[ORM\Column(name: 'to_status_id', type: 'string', length: 32)]
    private string $toStatusId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $actor;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $issueId,
        ?string $fromStatusId,
        string $toStatusId,
        string $actor,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->issueId = $issueId;
        $this->fromStatusId = $fromStatusId;
        $this->toStatusId = $toStatusId;
        $this->actor = $actor;
        $this->createdAt = $createdAt;
    }

    public function id(): string { return $this->id; }
    public function workspaceId(): string { return $this->workspaceId; }
    public function issueId(): string { return $this->issueId; }
    public function fromStatusId(): ?string { return $this->fromStatusId; }
    public function toStatusId(): string { return $this->toStatusId; }
    public function actor(): string { return $this->actor; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
}
