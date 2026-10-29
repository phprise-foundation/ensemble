<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

final readonly class ProbeResult
{
    private function __construct(
        private bool $ok,
        private string $message,
    ) {
    }

    public static function success(string $message = 'connection ok'): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    public function message(): string
    {
        return $this->message;
    }
}
