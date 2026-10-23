<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Domain\Team\Agent;

final class AgentPresenter
{
    /** @return array<string, mixed> */
    public function present(Agent $agent): array
    {
        return [
            'id' => $agent->id()->value(),
            'workspaceId' => $agent->workspaceId()->value(),
            'teamId' => $agent->teamId()->value(),
            'name' => $agent->name(),
            'whoAmI' => $agent->whoAmI(),
            'age' => $agent->age(),
            'maritalStatus' => $agent->maritalStatus()->value,
            'roleIds' => $agent->roleIds()->values(),
            'profileId' => $agent->profileId()?->value(),
            'avatarColor' => $agent->avatarColor()->value(),
            'portrait' => $agent->portrait()->value(),
            'model' => $agent->model(),
            'fallbackModel' => $agent->fallbackModel(),
            'interval' => $agent->interval()->value(),
            'active' => $agent->active(),
            'createdAt' => $agent->createdAt()->format(DATE_ATOM),
            'updatedAt' => $agent->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Agent> $agents
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $agents): array
    {
        return array_map(fn(Agent $agent): array => $this->present($agent), $agents);
    }
}
