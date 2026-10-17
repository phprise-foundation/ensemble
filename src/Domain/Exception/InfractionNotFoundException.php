<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Infraction\InfractionId;
use RuntimeException;

final class InfractionNotFoundException extends RuntimeException
{
    public static function forId(InfractionId $id): self
    {
        return new self(sprintf('Infraction #%d was not found.', $id->toInt()));
    }
}
