<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\Workflow;

final class WorkflowPresenter
{
    /** @return array<string, mixed> */
    public function present(Workflow $workflow): array
    {
        return [
            'id' => $workflow->id()->value(),
            'workspaceId' => $workflow->workspaceId()->value(),
            'key' => $workflow->key()->value(),
            'name' => $workflow->name(),
            'createdAt' => $workflow->createdAt()->format(DATE_ATOM),
            'updatedAt' => $workflow->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Workflow> $workflows
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $workflows): array
    {
        return array_map(fn(Workflow $workflow): array => $this->present($workflow), $workflows);
    }
}
