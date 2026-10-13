<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Workflow\ColumnId;
use RuntimeException;

final class ColumnAlreadyExistsException extends RuntimeException
{
    public static function forId(ColumnId $id): self
    {
        return new self(sprintf('Column "%s" already exists.', $id->value()));
    }
}
