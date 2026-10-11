<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Board\ItemId;
use RuntimeException;

final class ItemNotFoundException extends RuntimeException
{
    public static function forId(ItemId $id): self
    {
        return new self(sprintf('Item #%d was not found.', $id->toInt()));
    }
}
