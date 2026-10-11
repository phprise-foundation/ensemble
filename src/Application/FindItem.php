<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Contract\BoardRepository;
use App\Domain\Exception\ItemNotFoundException;

final readonly class FindItem
{
    public function __construct(private BoardRepository $repository)
    {
    }

    public function execute(ItemId $id): Item
    {
        return $this->repository->find($id) ?? throw ItemNotFoundException::forId($id);
    }
}
