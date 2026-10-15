<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Agent\AgentId;
use RuntimeException;

final class AgentAlreadyExistsException extends RuntimeException
{
    public static function forId(AgentId $id): self
    {
        return new self(sprintf('Agent "%s" already exists.', $id->value()));
    }
}
