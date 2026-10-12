<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

interface HttpTransport
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function post(string $url, array $headers, string $body, int $timeout): array;
}
