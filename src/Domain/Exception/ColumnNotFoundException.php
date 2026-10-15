<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Workflow\ColumnId;
use RuntimeException;

final class ColumnNotFoundException extends RuntimeException
{
    public static function forId(ColumnId $id): self
    {
        return new self(sprintf('Column "%s" was not found.', $id->value()));
    }

    public static function noneConfigured(): self
    {
        return new self('The board has no columns configured.');
    }
}
