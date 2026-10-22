<?php

declare(strict_types=1);

namespace App\Interface\Http;

use Psr\Http\Message\ResponseInterface;

final class ResponseEmitter
{
    public static function emit(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        /** @var array<string, list<string>> $headers */
        $headers = $response->getHeaders();
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        echo (string) $response->getBody();
    }
}
