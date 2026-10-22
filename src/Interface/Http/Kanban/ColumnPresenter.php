<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\StatusId;

final class ColumnPresenter
{
    /** @return array<string, mixed> */
    public function present(BoardColumn $column): array
    {
        return [
            'id' => $column->id()->value(),
            'boardId' => $column->boardId()->value(),
            'name' => $column->name(),
            'position' => $column->position()->value(),
            'wip' => $column->wip(),
            'statusIds' => array_map(static fn(StatusId $id): string => $id->value(), $column->statusIds()->all()),
            'createdAt' => $column->createdAt()->format(DATE_ATOM),
            'updatedAt' => $column->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<BoardColumn> $columns
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $columns): array
    {
        return array_map(fn(BoardColumn $column): array => $this->present($column), $columns);
    }
}
