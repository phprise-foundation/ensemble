<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

final readonly class MergeResult
{
    private function __construct(
        private bool $failed,
        private string $message,
        private ?string $sha,
    ) {
    }

    public static function merged(string $message, ?string $sha = null): self
    {
        return new self(false, $message, $sha);
    }

    public static function failed(string $message): self
    {
        return new self(true, $message, null);
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function sha(): ?string
    {
        return $this->sha;
    }
}
