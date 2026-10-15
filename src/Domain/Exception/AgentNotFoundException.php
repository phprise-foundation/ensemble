<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Agent\AgentId;
use RuntimeException;

final class AgentNotFoundException extends RuntimeException
{
    public static function forId(AgentId $id): self
    {
        return new self(sprintf('Agent "%s" was not found.', $id->value()));
    }
}
