<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Domain\Team\Role;

final class RolePresenter
{
    /** @return array<string, mixed> */
    public function present(Role $role): array
    {
        return [
            'id' => $role->id()->value(),
            'workspaceId' => $role->workspaceId()->value(),
            'teamId' => $role->teamId()->value(),
            'name' => $role->name(),
            'description' => $role->description(),
            'unblock' => $role->unblock(),
            'interactive' => $role->interactive(),
            'createdAt' => $role->createdAt()->format(DATE_ATOM),
            'updatedAt' => $role->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Role> $roles
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $roles): array
    {
        return array_map(fn(Role $role): array => $this->present($role), $roles);
    }
}
