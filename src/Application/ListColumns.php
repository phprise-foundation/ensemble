<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Contract\ColumnRepository;
use App\Domain\Workflow\ColumnCollection;

final readonly class ListColumns
{
    public function __construct(private ColumnRepository $columns)
    {
    }

    public function execute(): ColumnCollection
    {
        return $this->columns->all();
    }
}
