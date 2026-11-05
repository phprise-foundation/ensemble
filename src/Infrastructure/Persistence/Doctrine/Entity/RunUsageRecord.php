<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'run_usage')]
#[ORM\Index(name: 'idx_run_usage_run', columns: ['run_id'])]
#[ORM\Index(name: 'idx_run_usage_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_run_usage_agent', columns: ['agent_id'])]
#[ORM\Index(name: 'idx_run_usage_issue', columns: ['issue_id'])]
#[ORM\Index(name: 'idx_run_usage_model', columns: ['model_id'])]
#[ORM\Index(name: 'idx_run_usage_created', columns: ['created_at'])]
final class RunUsageRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'run_id', type: 'string', length: 32)]
    private string $runId;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'agent_id', type: 'string', length: 32)]
    private string $agentId;

    #[ORM\Column(name: 'issue_id', type: 'string', length: 32)]
    private string $issueId;

    #[ORM\Column(name: 'model_id', type: 'string', length: 80)]
    private string $modelId;

    #[ORM\Column(type: 'string', length: 8)]
    private string $plan;

    #[ORM\Column(name: 'provider_model', type: 'string', length: 120)]
    private string $providerModel;

    #[ORM\Column(type: 'integer')]
    private int $requests;

    #[ORM\Column(name: 'input_tokens', type: 'integer')]
    private int $inputTokens;

    #[ORM\Column(name: 'output_tokens', type: 'integer')]
    private int $outputTokens;

    #[ORM\Column(name: 'reasoning_tokens', type: 'integer')]
    private int $reasoningTokens;

    #[ORM\Column(name: 'cache_read_tokens', type: 'integer')]
    private int $cacheReadTokens;

    #[ORM\Column(name: 'cache_write_tokens', type: 'integer')]
    private int $cacheWriteTokens;

    #[ORM\Column(name: 'actual_cost_micros', type: 'integer')]
    private int $actualCostMicros;

    #[ORM\Column(name: 'charged_cost_micros', type: 'integer')]
    private int $chargedCostMicros;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $runId,
        string $workspaceId,
        string $agentId,
        string $issueId,
        string $modelId,
        string $plan,
        string $providerModel,
        int $requests,
        int $inputTokens,
        int $outputTokens,
        int $reasoningTokens,
        int $cacheReadTokens,
        int $cacheWriteTokens,
        int $actualCostMicros,
        int $chargedCostMicros,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->runId = $runId;
        $this->workspaceId = $workspaceId;
        $this->agentId = $agentId;
        $this->issueId = $issueId;
        $this->modelId = $modelId;
        $this->plan = $plan;
        $this->providerModel = $providerModel;
        $this->requests = $requests;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
        $this->reasoningTokens = $reasoningTokens;
        $this->cacheReadTokens = $cacheReadTokens;
        $this->cacheWriteTokens = $cacheWriteTokens;
        $this->actualCostMicros = $actualCostMicros;
        $this->chargedCostMicros = $chargedCostMicros;
        $this->createdAt = $createdAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function runId(): string
    {
        return $this->runId;
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

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function plan(): string
    {
        return $this->plan;
    }

    public function providerModel(): string
    {
        return $this->providerModel;
    }

    public function requests(): int
    {
        return $this->requests;
    }

    public function inputTokens(): int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): int
    {
        return $this->outputTokens;
    }

    public function reasoningTokens(): int
    {
        return $this->reasoningTokens;
    }

    public function cacheReadTokens(): int
    {
        return $this->cacheReadTokens;
    }

    public function cacheWriteTokens(): int
    {
        return $this->cacheWriteTokens;
    }

    public function actualCostMicros(): int
    {
        return $this->actualCostMicros;
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
