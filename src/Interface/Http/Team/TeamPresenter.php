<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Domain\Team\Team;

final class TeamPresenter
{
    /** @return array<string, mixed> */
    public function present(Team $team): array
    {
        return [
            'id' => $team->id()->value(),
            'workspaceId' => $team->workspaceId()->value(),
            'name' => $team->name(),
            'createdAt' => $team->createdAt()->format(DATE_ATOM),
            'updatedAt' => $team->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Team> $teams
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $teams): array
    {
        return array_map(fn(Team $team): array => $this->present($team), $teams);
    }
}
