<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Workflow\Board;

interface BoardSettings
{
    public function get(): Board;

    public function save(Board $board): void;
}
