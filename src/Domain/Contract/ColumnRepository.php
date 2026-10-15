<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Workflow\Column;
use App\Domain\Workflow\ColumnCollection;
use App\Domain\Workflow\ColumnId;

interface ColumnRepository
{
    public function all(): ColumnCollection;

    public function find(ColumnId $id): ?Column;

    public function save(Column $column): void;

    public function delete(ColumnId $id): void;
}
