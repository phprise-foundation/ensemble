<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\Agent;
use App\Domain\Agent\AgentId;
use App\Domain\Contract\AgentRegistry;
use App\Domain\Exception\AgentNotFoundException;

final readonly class UpdateAgent
{
    public function __construct(private AgentRegistry $agents)
    {
    }

    public function execute(
        AgentId $id,
        ?string $name = null,
        ?string $description = null,
        ?bool $active = null,
        ?string $rule = null,
        ?string $profile = null,
        ?string $model = null,
        ?string $fallbackModel = null,
        ?int $interval = null,
        ?string $workspace = null,
    ): Agent {
        $agent = $this->agents->find($id) ?? throw AgentNotFoundException::forId($id);
        $this->applyName($agent, $name);
        $this->applyDescription($agent, $description);
        $this->applyActive($agent, $active);
        $this->applySpec($agent, $rule, $profile, $model, $fallbackModel, $interval, $workspace);
        $this->agents->save($agent);

        return $agent;
    }

    private function applyName(Agent $agent, ?string $name): void
    {
        if ($name !== null && $name !== '') {
            $agent->rename($name);
        }
    }

    private function applyDescription(Agent $agent, ?string $description): void
    {
        if ($description !== null) {
            $agent->describe($description);
        }
    }

    private function applyActive(Agent $agent, ?bool $active): void
    {
        if ($active !== null) {
            $agent->activate($active);
        }
    }

    private function applySpec(
        Agent $agent,
        ?string $rule,
        ?string $profile,
        ?string $model,
        ?string $fallbackModel,
        ?int $interval,
        ?string $workspace,
    ): void {
        $agent->configure(
            $rule ?? $agent->rule(),
            $profile ?? $agent->profile(),
            $model ?? $agent->model(),
            $fallbackModel ?? $agent->fallbackModel(),
            $interval ?? $agent->interval(),
            $workspace ?? $agent->workspace(),
        );
    }
}
