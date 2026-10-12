<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class ChatUnavailableException extends RuntimeException
{
    public static function fromStatus(int $status): self
    {
        return new self(sprintf('Chat service returned status %d.', $status));
    }

    public static function transport(): self
    {
        return new self('Chat service is unreachable.');
    }
}
