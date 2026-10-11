<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Board\Item;
use App\Domain\Board\ItemId;

interface BoardRepository
{
    /** @return Item[] */
    public function all(): array;

    public function find(ItemId $id): ?Item;

    public function nextId(): ItemId;

    public function add(Item $item): void;

    public function save(Item $item): void;
}
