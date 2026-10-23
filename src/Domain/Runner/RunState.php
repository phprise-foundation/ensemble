<?php

declare(strict_types=1);

namespace App\Domain\Runner;

enum RunState: string
{
    case Claimed = 'claimed';

    case Running = 'running';

    case Succeeded = 'succeeded';

    case Failed = 'failed';

    case Skipped = 'skipped';

    /** @return list<RunState> */
    public static function active(): array
    {
        return [self::Claimed, self::Running];
    }

    public function isActive(): bool
    {
        return $this === self::Claimed || $this === self::Running;
    }

    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }
}
