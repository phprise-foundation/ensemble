<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Workflow\Board;
use App\Domain\Workflow\Column;
use App\Domain\Workflow\ColumnCollection;
use App\Domain\Workflow\ColumnId;

final class BoardPresenter
{
    public function present(Board $board, ColumnCollection $columns): array
    {
        return [
            'name' => $board->name(),
            'aging_hours' => $board->agingHours(),
            'columns' => array_values(array_map(fn(Column $column): array => $this->presentColumn($column), $columns->all())),
        ];
    }

    public function presentColumn(Column $column): array
    {
        return [
            'id' => $column->id()->value(),
            'name' => $column->name(),
            'position' => $column->position()->value(),
            'final' => $column->isFinal(),
            'role' => $column->role()?->value(),
            'transitions' => array_values(array_map(
                static fn(ColumnId $id): string => $id->value(),
                $column->transitions()->all(),
            )),
        ];
    }
}
