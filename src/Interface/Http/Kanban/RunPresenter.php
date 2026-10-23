<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Runner\Run;

final class RunPresenter
{
    /** @return array<string, mixed> */
    public function present(Run $run): array
    {
        return [
            'id' => $run->id()->value(),
            'workspaceId' => $run->workspaceId()->value(),
            'agentId' => $run->agentId()->value(),
            'issueId' => $run->issueId()->value(),
            'state' => $run->state()->value,
            'attempt' => $run->attempt(),
            'reason' => $run->reason(),
            'message' => $run->message(),
            'prompt' => $run->prompt(),
            'createdAt' => $run->createdAt()->format(DATE_ATOM),
            'startedAt' => $run->startedAt()?->format(DATE_ATOM),
            'finishedAt' => $run->finishedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Run> $runs
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $runs): array
    {
        return array_map(fn(Run $run): array => $this->present($run), $runs);
    }
}
