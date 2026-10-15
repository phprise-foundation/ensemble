<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Contract\ColumnRepository;
use App\Domain\Contract\ItemRepository;
use App\Domain\Exception\ColumnNotFoundException;
use App\Domain\Exception\ColumnNotRemovableException;
use App\Domain\Workflow\ColumnId;

final readonly class DeleteColumn
{
    public function __construct(
        private ColumnRepository $columns,
        private ItemRepository $items,
    ) {
    }

    public function execute(ColumnId $id): void
    {
        $column = $this->columns->find($id) ?? throw ColumnNotFoundException::forId($id);
        if ($column->isFinal()) {
            throw ColumnNotRemovableException::isFinal($id);
        }
        if ($this->items->countInColumn($id) > 0) {
            throw ColumnNotRemovableException::inUse($id);
        }
        $this->columns->delete($id);
    }
}
