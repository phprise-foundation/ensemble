<?php

declare(strict_types=1);

namespace App\Domain\Runner;

final readonly class Outcome
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
    ) {
    }

    public static function succeeded(string $message = ''): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
