<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Exception\ValidationException;

enum BranchSyncStrategy: string
{
    case Merge = 'merge';

    case KeepWork = 'keep_work';

    case KeepBase = 'keep_base';

    public static function fromString(string $value): self
    {
        $case = self::tryFrom($value);
        if ($case === null) {
            throw new ValidationException('strategy', 'Strategy must be merge, keep_work or keep_base.');
        }

        return $case;
    }
}
