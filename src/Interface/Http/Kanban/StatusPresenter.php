<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\Status;

final class StatusPresenter
{
    /** @return array<string, mixed> */
    public function present(Status $status): array
    {
        return [
            'id' => $status->id()->value(),
            'workflowId' => $status->workflowId()->value(),
            'issueTypeId' => $status->issueTypeId()->value(),
            'key' => $status->key()->value(),
            'name' => $status->name(),
            'type' => $status->type()->value,
            'color' => $status->color(),
            'position' => $status->position()->value(),
            'roleIds' => $status->roleIds()->values(),
            'createdAt' => $status->createdAt()->format(DATE_ATOM),
            'updatedAt' => $status->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Status> $statuses
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $statuses): array
    {
        return array_map(fn(Status $status): array => $this->present($status), $statuses);
    }
}
