<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Trigger\Trigger;

interface TriggerRepository
{
    /** @return Trigger[] */
    public function forEvent(string $eventType): array;
}
