<?php

declare(strict_types=1);

namespace App\Infrastructure\Vcs;

final readonly class GitHubResponse
{
    public function __construct(
        private int $status,
        private string $body,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }
}
