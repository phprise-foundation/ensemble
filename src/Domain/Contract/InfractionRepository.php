<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Board\ItemId;
use App\Domain\Infraction\Infraction;
use App\Domain\Infraction\InfractionId;

interface InfractionRepository
{
    public function nextId(): InfractionId;

    public function add(Infraction $infraction): void;

    public function save(Infraction $infraction): void;

    public function find(InfractionId $id): ?Infraction;

    /** @return Infraction[] */
    public function forItem(ItemId $itemId): array;
}
