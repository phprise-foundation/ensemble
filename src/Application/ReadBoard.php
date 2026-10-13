<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Contract\BoardSettings;
use App\Domain\Workflow\Board;

final readonly class ReadBoard
{
    public function __construct(private BoardSettings $settings)
    {
    }

    public function execute(): Board
    {
        return $this->settings->get();
    }
}
