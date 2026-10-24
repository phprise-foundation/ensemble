<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Exception\ValidationException;

enum ResolutionStrategy: string
{
    case RemoteWins = 'remote_wins';

    case LocalWins = 'local_wins';

    case Merge = 'merge';

    public static function fromString(string $value): self
    {
        $case = self::tryFrom($value);
        if ($case === null) {
            throw new ValidationException('strategy', 'Strategy must be remote_wins, local_wins or merge.');
        }

        return $case;
    }
}
