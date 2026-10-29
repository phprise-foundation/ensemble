<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

final readonly class PullRequestResult
{
    private function __construct(
        private bool $failed,
        private ?string $url,
        private string $message,
    ) {
    }

    public static function opened(string $url): self
    {
        return new self(false, $url, 'pull request opened');
    }

    public static function skipped(string $message): self
    {
        return new self(false, null, $message);
    }

    public static function failed(string $message): self
    {
        return new self(true, null, $message);
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function message(): string
    {
        return $this->message;
    }
}
