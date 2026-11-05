<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Kanban\IssueId;
use App\Domain\Model\ModelId;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunUsage
{
    public function __construct(
        private RunUsageId $id,
        private RunId $runId,
        private WorkspaceId $workspaceId,
        private AgentId $agentId,
        private IssueId $issueId,
        private ModelId $modelId,
        private ModelUsage $usage,
        private int $chargedCostMicros,
        private DateTimeImmutable $createdAt,
    ) {
        if ($chargedCostMicros < 0) {
            throw new InvalidArgumentException('The charged cost must not be negative.');
        }
    }

    public function id(): RunUsageId
    {
        return $this->id;
    }

    public function runId(): RunId
    {
        return $this->runId;
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

    public function modelId(): ModelId
    {
        return $this->modelId;
    }

    public function usage(): ModelUsage
    {
        return $this->usage;
    }

    public function chargedCostMicros(): int
    {
        return $this->chargedCostMicros;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
