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
use App\Domain\Exception\InvalidTransitionException;
use App\Domain\Exception\ItemNotFoundException;
use App\Domain\Workflow\Column;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;

final readonly class AdvanceItem
{
    public function __construct(
        private ItemRepository $items,
        private ColumnRepository $columns,
        private ItemEventLog $events,
    ) {
    }

    public function execute(ItemId $id, ?ColumnId $to = null, string $actor = 'user'): Item
    {
        $item = $this->items->find($id) ?? throw ItemNotFoundException::forId($id);
        $current = $this->column($item->column());
        $target = $this->resolve($current, $to);
        $at = new DateTimeImmutable();
        $item->moveTo($target->id(), $at);
        $item->assignAgent($target->role(), $at);
        $this->items->save($item);
        $this->events->record($id, new ItemEvent($current->id(), $target->id(), $actor, $at));

        return $item;
    }

    private function resolve(Column $current, ?ColumnId $to): Column
    {
        $target = $to ?? $current->transitions()->sole() ?? throw InvalidTransitionException::missing($current->id());
        if (!$current->transitions()->allows($target)) {
            throw InvalidTransitionException::forbidden($current->id(), $target);
        }

        return $this->column($target);
    }

    private function column(ColumnId $id): Column
    {
        return $this->columns->find($id) ?? throw ColumnNotFoundException::forId($id);
    }
}
