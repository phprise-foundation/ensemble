<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Item;
use App\Domain\Board\ItemEvent;
use App\Domain\Board\ItemId;
use App\Domain\Contract\ColumnRepository;
use App\Domain\Contract\ItemEventLog;
use App\Domain\Contract\ItemRepository;
use App\Domain\Exception\ColumnNotFoundException;
use App\Domain\Exception\ItemNotFoundException;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;

final readonly class UpdateItem
{
    public function __construct(
        private ItemRepository $items,
        private ColumnRepository $columns,
        private ItemEventLog $events,
    ) {
    }

    public function execute(ItemId $id, ItemChanges $changes, string $actor = 'user'): Item
    {
        $item = $this->items->find($id) ?? throw ItemNotFoundException::forId($id);
        $previous = $item->column();
        $at = new DateTimeImmutable();
        $changes->applyTo($item, $at);
        $this->guardColumn($item->column());
        $this->items->save($item);
        $this->recordMove($item, $previous, $actor, $at);

        return $item;
    }

    private function guardColumn(ColumnId $column): void
    {
        if ($this->columns->find($column) === null) {
            throw ColumnNotFoundException::forId($column);
        }
    }

    private function recordMove(Item $item, ColumnId $previous, string $actor, DateTimeImmutable $at): void
    {
        if ($item->column()->equals($previous)) {
            return;
        }
        $this->events->record($item->id(), new ItemEvent($previous, $item->column(), $actor, $at));
    }
}
