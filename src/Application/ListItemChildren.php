<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\ItemId;
use App\Domain\Contract\ItemRepository;

final readonly class ListItemChildren
{
    public function __construct(private ItemRepository $items)
    {
    }

    /** @return ItemId[] */
    public function execute(ItemId $id): array
    {
        return $this->items->childrenOf($id);
    }
}
