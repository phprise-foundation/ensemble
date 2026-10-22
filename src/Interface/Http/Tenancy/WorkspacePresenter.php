<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Application\Tenancy\WorkspaceSummary;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\Workspace;

final class WorkspacePresenter
{
    /** @return array<string, mixed> */
    public function present(Workspace $workspace, ?MembershipRole $role): array
    {
        return [
            'id' => $workspace->id()->value(),
            'name' => $workspace->name(),
            'prefix' => $workspace->prefix()->value(),
            'role' => $role?->value,
            'createdAt' => $workspace->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<WorkspaceSummary> $workspaces
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $workspaces): array
    {
        return array_map(
            fn(WorkspaceSummary $summary): array => $this->present($summary->workspace(), $summary->role()),
            $workspaces,
        );
    }
}
