<?php

declare(strict_types=1);

namespace App\Domain\Contract\Runner;

use App\Domain\Runner\AgentUsage;
use App\Domain\Runner\RunUsage;
use App\Domain\Runner\WorkspaceUsage;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

interface RunUsageRepository
{
    public function record(RunUsage $usage): void;

    public function chargedSince(AgentId $agentId, DateTimeImmutable $since): int;

    public function usageSince(AgentId $agentId, DateTimeImmutable $since): AgentUsage;

    public function chargedForWorkspace(WorkspaceId $workspaceId): int;

    public function summaryForWorkspace(WorkspaceId $workspaceId, ?DateTimeImmutable $since = null): WorkspaceUsage;
}
