<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Item;
use App\Domain\Board\Role;
use App\Domain\Board\Status;
use App\Domain\Contract\BoardRepository;
use DateTimeImmutable;

final readonly class CreateItem
{
    public function __construct(private BoardRepository $repository)
    {
    }

    public function execute(string $title, string $body, ?Status $status, ?Role $role): Item
    {
        $item = new Item(
            $this->repository->nextId(),
            $title,
            $body,
            $status ?? Status::Intent,
            $role,
            new DateTimeImmutable(),
        );
        $this->repository->add($item);

        return $item;
    }
}
