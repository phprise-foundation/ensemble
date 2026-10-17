<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\AgentId;
use App\Domain\Board\IssueType;
use App\Domain\Board\Item;
use App\Domain\Board\ItemEvent;
use App\Domain\Board\ItemId;
use App\Domain\Contract\ColumnRepository;
use App\Domain\Contract\ItemEventLog;
use App\Domain\Contract\ItemRepository;
use App\Domain\Exception\ColumnNotFoundException;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;

final readonly class CreateItem
{
    public function __construct(
        private ItemRepository $items,
        private ColumnRepository $columns,
        private ItemEventLog $events,
    ) {
    }

    public function execute(
        string $title,
        string $body,
        ?ColumnId $column,
        ?AgentId $agent,
        string $actor = 'user',
        IssueType $type = IssueType::Task,
        ?ItemId $parent = null,
    ): Item {
        $target = $column ?? $this->defaultColumn();
        $this->guardColumn($target);
        $at = new DateTimeImmutable();
        $item = new Item($this->items->nextId(), $target, $title, $body, $agent, $at, $type, $parent);
        $this->items->add($item);
        $this->events->record($item->id(), new ItemEvent(null, $target, $actor, $at));

        return $item;
    }

    private function defaultColumn(): ColumnId
    {
        return $this->columns->all()->first()?->id() ?? throw ColumnNotFoundException::noneConfigured();
    }

    private function guardColumn(ColumnId $column): void
    {
        if ($this->columns->find($column) === null) {
            throw ColumnNotFoundException::forId($column);
        }
    }
}
