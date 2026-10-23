<?php

declare(strict_types=1);

namespace App\Interface\Http\Security;

use App\Domain\Security\WorkspaceSecret;

final class SecretPresenter
{
    /** @return array<string, mixed> */
    public function present(WorkspaceSecret $secret): array
    {
        return [
            'id' => $secret->id()->value(),
            'workspaceId' => $secret->workspaceId()->value(),
            'name' => $secret->name()->value(),
            'createdAt' => $secret->createdAt()->format(DATE_ATOM),
            'updatedAt' => $secret->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<WorkspaceSecret> $secrets
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $secrets): array
    {
        return array_map(fn(WorkspaceSecret $secret): array => $this->present($secret), $secrets);
    }
}
