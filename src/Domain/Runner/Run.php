<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Kanban\IssueId;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use RuntimeException;

final class Run
{
    private RunState $state;

    private ?string $reason;

    private ?DateTimeImmutable $startedAt;

    public function __construct(
        private readonly RunId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly AgentId $agentId,
        private readonly IssueId $issueId,
        RunState $state = RunState::Claimed,
        ?string $reason = null,
        private int $attempt = 1,
        private ?DateTimeImmutable $leaseExpiresAt = null,
        ?DateTimeImmutable $startedAt = null,
        private ?string $prompt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private ?string $message = null,
        private ?DateTimeImmutable $finishedAt = null,
        private ?string $pullRequestUrl = null,
    ) {
        if ($state === RunState::Running && ($startedAt === null || $leaseExpiresAt === null)) {
            throw new RuntimeException('A running run requires its start time and lease.');
        }
        $this->state = $state;
        $this->reason = $reason;
        $this->message = $message;
        $this->finishedAt = $finishedAt;
        $this->startedAt = $startedAt;
    }

    public function id(): RunId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function agentId(): AgentId
    {
        return $this->agentId;
    }

    public function issueId(): IssueId
    {
        return $this->issueId;
    }

    public function state(): RunState
    {
        return $this->state;
    }

    public function reason(): ?string
    {
        return $this->reason;
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

    public function prompt(): ?string
    {
        return $this->prompt;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function pullRequestUrl(): ?string
    {
        return $this->pullRequestUrl;
    }

    public function attachPullRequest(string $url): void
    {
        $this->pullRequestUrl = $url;
    }

    public function lease(DateTimeImmutable $now, int $seconds): void
    {
        $this->leaseExpiresAt = $now->modify('+' . $seconds . ' seconds');
    }

    public function start(string $prompt, DateTimeImmutable $now, int $leaseSeconds): void
    {
        if ($this->state === RunState::Skipped) {
            throw new RuntimeException('A skipped run cannot start.');
        }

        $this->state = RunState::Running;
        $this->startedAt = $now;
        $this->prompt = $prompt;
        $this->lease($now, $leaseSeconds);
    }

    public function complete(Outcome $outcome, DateTimeImmutable $now): void
    {
        if ($outcome->ok) {
            $this->state = RunState::Succeeded;
        } else {
            $this->state = RunState::Failed;
            $this->reason = $outcome->message;
        }

        $this->message = $outcome->message;
        $this->finishedAt = $now;
        $this->leaseExpiresAt = null;
    }

    public function skip(string $reason, DateTimeImmutable $now): void
    {
        if ($this->state !== RunState::Claimed) {
            throw new RuntimeException('Only a claimed run can be skipped.');
        }

        $this->state = RunState::Skipped;
        $this->reason = $reason;
        $this->finishedAt = $now;
        $this->leaseExpiresAt = null;
    }

    public function reclaim(DateTimeImmutable $now, int $seconds, int $maxAttempts): void
    {
        if ($this->attempt >= $maxAttempts) {
            $this->state = RunState::Failed;
            $this->reason = 'the run lost its lease too many times';
            $this->finishedAt = $now;
            $this->leaseExpiresAt = null;

            return;
        }

        if (! $this->isLeaseExpired($now)) {
            return;
        }

        $this->attempt++;
        $this->state = RunState::Claimed;
        $this->startedAt = null;
        $this->prompt = null;
        $this->lease($now, $seconds);
    }

    public function isLeaseExpired(DateTimeImmutable $now): bool
    {
        return $this->leaseExpiresAt !== null && $this->leaseExpiresAt <= $now;
    }
}
