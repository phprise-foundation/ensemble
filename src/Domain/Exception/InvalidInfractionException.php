<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class InvalidInfractionException extends RuntimeException
{
    public static function evidenceRequired(): self
    {
        return new self('An infraction requires evidence.');
    }

    public static function selfInfraction(): self
    {
        return new self('An agent cannot report its own infraction.');
    }

    public static function invalidVerdict(): self
    {
        return new self('An adjudication must be "upheld" or "dismissed".');
    }

    public static function alreadyAdjudicated(): self
    {
        return new self('This infraction was already adjudicated.');
    }

    public static function impartiality(): self
    {
        return new self('The adjudicator must not be the infractor or the detector.');
    }
}
