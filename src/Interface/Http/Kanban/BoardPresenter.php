<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\Board;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;

final class BoardPresenter
{
    /** @return array<string, mixed> */
    public function present(Board $board): array
    {
        return [
            'id' => $board->id()->value(),
            'workspaceId' => $board->workspaceId()->value(),
            'workflowId' => $board->workflowId()->value(),
            'key' => $board->key()->value(),
            'name' => $board->name(),
            'filters' => [
                'issueTypeIds' => array_map(static fn(IssueTypeId $id): string => $id->value(), $board->filters()->issueTypeIds()),
                'statusIds' => array_map(static fn(StatusId $id): string => $id->value(), $board->filters()->statusIds()),
            ],
            'createdAt' => $board->createdAt()->format(DATE_ATOM),
            'updatedAt' => $board->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Board> $boards
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $boards): array
    {
        return array_map(fn(Board $board): array => $this->present($board), $boards);
    }
}
