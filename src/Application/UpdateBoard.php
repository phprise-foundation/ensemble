<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Contract\BoardSettings;
use App\Domain\Workflow\Board;

final readonly class UpdateBoard
{
    public function __construct(private BoardSettings $settings)
    {
    }

    public function execute(?string $name, ?int $agingHours): Board
    {
        $board = $this->settings->get();
        if ($name !== null && $name !== '') {
            $board->rename($name);
        }
        if ($agingHours !== null) {
            $board->withAgingWindow($agingHours);
        }
        $this->settings->save($board);

        return $board;
    }
}
