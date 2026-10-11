<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Contract\BoardRepository;
use App\Domain\Exception\ItemNotFoundException;

final readonly class UpdateItem
{
    public function __construct(private BoardRepository $repository)
    {
    }

    public function execute(ItemId $id, ItemChanges $changes): Item
    {
        $item = $this->find($id);
        $changes->applyTo($item);
        $this->repository->save($item);

        return $item;
    }

    private function find(ItemId $id): Item
    {
        return $this->repository->find($id) ?? throw ItemNotFoundException::forId($id);
    }
}
