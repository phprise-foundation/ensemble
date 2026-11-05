<?php

declare(strict_types=1);

namespace App\Domain\Runner;

final readonly class Outcome
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly ?UsageReport $usage = null,
    ) {
    }

    public static function succeeded(string $message = '', ?UsageReport $usage = null): self
    {
        return new self(true, $message, $usage);
    }

    public static function failed(string $message, ?UsageReport $usage = null): self
    {
        return new self(false, $message, $usage);
    }
}
