<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'runs')]
#[ORM\Index(name: 'idx_runs_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_runs_issue', columns: ['issue_id'])]
#[ORM\Index(name: 'idx_runs_lease', columns: ['lease_expires_at'])]
final class RunRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'agent_id', type: 'string', length: 32)]
    private string $agentId;

    #[ORM\Column(name: 'issue_id', type: 'string', length: 32)]
    private string $issueId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $state;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $prompt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message;

    #[ORM\Column(type: 'integer')]
    private int $attempt;

    #[ORM\Column(name: 'lease_expires_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $leaseExpiresAt;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $finishedAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $agentId,
        string $issueId,
        string $state,
        ?string $reason,
        ?string $prompt,
        ?string $message,
        int $attempt,
        ?DateTimeImmutable $leaseExpiresAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->agentId = $agentId;
        $this->issueId = $issueId;
        $this->state = $state;
        $this->reason = $reason;
        $this->prompt = $prompt;
        $this->message = $message;
        $this->attempt = $attempt;
        $this->leaseExpiresAt = $leaseExpiresAt;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
        $this->createdAt = $createdAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function agentId(): string
    {
        return $this->agentId;
    }

    public function issueId(): string
    {
        return $this->issueId;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function prompt(): ?string
    {
        return $this->prompt;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function leaseExpiresAt(): ?DateTimeImmutable
    {
        return $this->leaseExpiresAt;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function sync(
        string $state,
        ?string $reason,
        ?string $prompt,
        ?string $message,
        int $attempt,
        ?DateTimeImmutable $leaseExpiresAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
    ): void {
        $this->state = $state;
        $this->reason = $reason;
        $this->prompt = $prompt;
        $this->message = $message;
        $this->attempt = $attempt;
        $this->leaseExpiresAt = $leaseExpiresAt;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }
}
