<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Item;
use App\Domain\Contract\ItemRepository;

final readonly class ListBoardItems
{
    public function __construct(private ItemRepository $items)
    {
    }

    /** @return Item[] */
    public function execute(): array
    {
        return $this->items->all();
    }
}
