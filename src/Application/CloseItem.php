<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Comment;
use App\Domain\Board\Item;
use App\Domain\Board\ItemEvent;
use App\Domain\Board\ItemId;
use App\Domain\Contract\ColumnRepository;
use App\Domain\Contract\ItemEventLog;
use App\Domain\Contract\ItemRepository;
use App\Domain\Exception\ItemNotFoundException;
use DateTimeImmutable;

final readonly class CloseItem
{
    public function __construct(
        private ItemRepository $items,
        private ColumnRepository $columns,
        private ItemEventLog $events,
    ) {
    }

    public function execute(ItemId $id, ?string $comment, string $actor = 'user'): Item
    {
        $item = $this->find($id);
        $at = new DateTimeImmutable();
        $this->moveToFinal($item, $actor, $at);
        $item->close($at);
        $this->appendClosingComment($item, $comment, $at);
        $this->items->save($item);

        return $item;
    }

    private function find(ItemId $id): Item
    {
        return $this->items->find($id) ?? throw ItemNotFoundException::forId($id);
    }

    private function moveToFinal(Item $item, string $actor, DateTimeImmutable $at): void
    {
        $final = $this->columns->all()->final();
        if ($final === null || $item->column()->equals($final->id())) {
            return;
        }
        $previous = $item->column();
        $item->moveTo($final->id(), $at);
        $this->events->record($item->id(), new ItemEvent($previous, $final->id(), $actor, $at));
    }

    private function appendClosingComment(Item $item, ?string $comment, DateTimeImmutable $at): void
    {
        if ($comment === null || $comment === '') {
            return;
        }
        $item->addComment(new Comment('system', $comment, $at), $at);
    }
}
