<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Workflow\ColumnId;
use RuntimeException;

final class ColumnNotRemovableException extends RuntimeException
{
    public static function inUse(ColumnId $id): self
    {
        return new self(sprintf('Column "%s" still holds items.', $id->value()));
    }

    public static function isFinal(ColumnId $id): self
    {
        return new self(sprintf('Column "%s" is the final stage and cannot be removed.', $id->value()));
    }
}
