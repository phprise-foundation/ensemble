<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use Psr\Http\Message\ServerRequestInterface;

final class JsonBody
{
    /** @return array<array-key, mixed> */
    public static function from(ServerRequestInterface $request): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $request->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<array-key, mixed> $body */
    public static function string(array $body, string $key): ?string
    {
        return is_string($body[$key] ?? null) ? (string) ($body[$key] ?? '') : null;
    }

    /** @param array<array-key, mixed> $body */
    public static function boolean(array $body, string $key): ?bool
    {
        return is_bool($body[$key] ?? null) ? (bool) ($body[$key] ?? false) : null;
    }
}
