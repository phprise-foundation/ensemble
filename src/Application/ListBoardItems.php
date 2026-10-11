<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Item;
use App\Domain\Contract\BoardRepository;

final readonly class ListBoardItems
{
    public function __construct(private BoardRepository $repository)
    {
    }

    /** @return Item[] */
    public function execute(): array
    {
        return $this->repository->all();
    }
}
