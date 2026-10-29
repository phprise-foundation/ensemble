<?php

declare(strict_types=1);

namespace App\Infrastructure\Vcs;

interface GitHubHttp
{
    /** @param array<string, mixed>|null $payload */
    public function request(string $method, string $url, ?string $token, ?array $payload = null): GitHubResponse;
}
