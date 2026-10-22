<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\Issue;

final class IssuePresenter
{
    /** @return array<string, mixed> */
    public function present(Issue $issue): array
    {
        return [
            'id' => $issue->id()->value(),
            'key' => $issue->key()->value(),
            'workspaceId' => $issue->workspaceId()->value(),
            'issueTypeId' => $issue->issueTypeId()->value(),
            'statusId' => $issue->statusId()->value(),
            'summary' => $issue->summary(),
            'description' => $issue->description(),
            'reporter' => $issue->reporter(),
            'assigneeId' => $issue->assigneeId(),
            'worker' => $issue->worker(),
            'blocked' => $issue->blocked(),
            'fields' => $issue->fieldValues()->all(),
            'createdAt' => $issue->createdAt()->format(DATE_ATOM),
            'updatedAt' => $issue->updatedAt()->format(DATE_ATOM),
            'enteredAt' => $issue->enteredAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Issue> $issues
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $issues): array
    {
        return array_map(fn(Issue $issue): array => $this->present($issue), $issues);
    }
}
