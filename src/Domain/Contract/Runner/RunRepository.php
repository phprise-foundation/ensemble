<?php

declare(strict_types=1);

namespace App\Domain\Contract\Runner;

use App\Domain\Kanban\IssueId;
use App\Domain\Runner\Run;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;

interface RunRepository
{
    public function add(Run $run): void;

    public function update(Run $run): void;

    /** The run that currently holds the issue, or null when free to work. */
    public function activeForIssue(IssueId $issueId): ?Run;

    /** The most recent run of this agent on this issue, or null. */
    public function latestFor(AgentId $agentId, IssueId $issueId): ?Run;

    /** @return list<Run> */
    public function withExpiredLeases(): array;

    /** @return list<Run> */
    public function forIssue(WorkspaceId $workspaceId, IssueId $issueId): array;
}
