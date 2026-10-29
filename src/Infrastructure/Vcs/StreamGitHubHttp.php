<?php

declare(strict_types=1);

namespace App\Infrastructure\Vcs;

use Override;

final readonly class StreamGitHubHttp implements GitHubHttp
{
    #[Override]
    public function request(string $method, string $url, ?string $token, ?array $payload = null): GitHubResponse
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: ensemble',
        ];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ];
        if ($payload !== null) {
            $options['http']['header'] .= "\r\nContent-Type: application/json";
            $options['http']['content'] = (string) json_encode($payload);
        }

        $body = @file_get_contents($url, false, stream_context_create($options));
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return new GitHubResponse($status, $body === false ? (error_get_last()['message'] ?? 'request failed') : $body);
    }
}
