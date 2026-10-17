<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\ItemId;
use App\Domain\Contract\InfractionRepository;
use App\Domain\Infraction\Infraction;

final readonly class ListItemInfractions
{
    public function __construct(private InfractionRepository $infractions)
    {
    }

    /** @return Infraction[] */
    public function execute(ItemId $itemId): array
    {
        return $this->infractions->forItem($itemId);
    }
}
