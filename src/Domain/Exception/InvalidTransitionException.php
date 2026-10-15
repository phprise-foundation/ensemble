<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Workflow\ColumnId;
use RuntimeException;

final class InvalidTransitionException extends RuntimeException
{
    public static function missing(ColumnId $from): self
    {
        return new self(sprintf('Column "%s" has no transition configured.', $from->value()));
    }

    public static function forbidden(ColumnId $from, ColumnId $to): self
    {
        return new self(sprintf('Cannot move from "%s" to "%s".', $from->value(), $to->value()));
    }
}
