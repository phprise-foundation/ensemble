<?php

declare(strict_types=1);

namespace App\Domain\Contract\Runner;

use App\Domain\Runner\RunUsage;

interface RunUsageRepository
{
    public function record(RunUsage $usage): void;
}
