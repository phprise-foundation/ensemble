<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\IssueType;

final class IssueTypePresenter
{
    /** @return array<string, mixed> */
    public function present(IssueType $issueType): array
    {
        return [
            'id' => $issueType->id()->value(),
            'workflowId' => $issueType->workflowId()->value(),
            'key' => $issueType->key()->value(),
            'name' => $issueType->name(),
            'level' => $issueType->level()->value(),
            'position' => $issueType->position()->value(),
            'createdAt' => $issueType->createdAt()->format(DATE_ATOM),
            'updatedAt' => $issueType->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<IssueType> $issueTypes
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $issueTypes): array
    {
        return array_map(fn(IssueType $issueType): array => $this->present($issueType), $issueTypes);
    }
}
