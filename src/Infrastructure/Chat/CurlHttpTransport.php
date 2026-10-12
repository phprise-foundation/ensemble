<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use App\Domain\Exception\ChatUnavailableException;
use Override;

final readonly class CurlHttpTransport implements HttpTransport
{
    #[Override]
    public function post(string $url, array $headers, string $body, int $timeout): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw ChatUnavailableException::transport();
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $this->headerLines($headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (!is_string($raw)) {
            throw ChatUnavailableException::transport();
        }

        return ['status' => $status, 'body' => $raw];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function headerLines(array $headers): array
    {
        return array_map(
            static fn(string $name, string $value): string => $name . ': ' . $value,
            array_keys($headers),
            array_values($headers),
        );
    }
}
