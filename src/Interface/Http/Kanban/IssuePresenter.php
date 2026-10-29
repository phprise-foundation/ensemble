<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Workdir;

final class IssuePresenter
{
    /** @return array<string, mixed> */
    public function present(Issue $issue, ?Workdir $workdir = null): array
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
            'parentKey' => $issue->parentKey()?->value(),
            'repositoryId' => $workdir?->repositoryId()?->value(),
            'workdirState' => $workdir?->state()->value,
            'createdAt' => $issue->createdAt()->format(DATE_ATOM),
            'updatedAt' => $issue->updatedAt()->format(DATE_ATOM),
            'enteredAt' => $issue->enteredAt()->format(DATE_ATOM),
            'position' => $issue->position(),
        ];
    }

    /**
     * @param list<Issue> $issues
     * @param array<string, Workdir> $workdirs
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $issues, array $workdirs = []): array
    {
        return array_map(
            function (Issue $issue) use ($workdirs): array {
                $workdirId = $issue->workdirId()?->value();

                return $this->present($issue, $workdirId === null ? null : ($workdirs[$workdirId] ?? null));
            },
            $issues,
        );
    }
}
