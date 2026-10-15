<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Board\ItemEvent;
use App\Domain\Board\ItemId;

interface ItemEventLog
{
    public function record(ItemId $id, ItemEvent $event): void;

    /** @return ItemEvent[] */
    public function history(ItemId $id): array;

    public function latest(ItemId $id): ?ItemEvent;
}
