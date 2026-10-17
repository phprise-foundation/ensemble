<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Agent\Agent;

final class AgentArrayPresenter
{
    public function present(Agent $agent): array
    {
        return [
            'id' => $agent->id()->value(),
            'name' => $agent->name(),
            'description' => $agent->description(),
            'active' => $agent->isActive(),
            'profile' => $agent->profile(),
            'model' => $agent->model(),
            'fallback_model' => $agent->fallbackModel(),
            'interval' => $agent->interval(),
            'workspace' => $agent->workspace(),
        ];
    }

    /** @param Agent[] $agents */
    public function presentMany(array $agents): array
    {
        return array_values(array_map(fn(Agent $agent): array => $this->present($agent), $agents));
    }
}
