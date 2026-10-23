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

    /** @param array<array-key, mixed> $body */
    public static function integer(array $body, string $key): ?int
    {
        return is_int($body[$key] ?? null) ? (int) ($body[$key] ?? 0) : null;
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<string, mixed>|null
     */
    public static function object(array $body, string $key): ?array
    {
        $value = $body[$key] ?? null;
        if (!is_array($value) || !self::hasStringKeys($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @param array<array-key, mixed> $value */
    private static function hasStringKeys(array $value): bool
    {
        foreach (array_keys($value) as $candidate) {
            if (!is_string($candidate)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $body
     * @return list<string>|null
     */
    public static function stringList(array $body, string $key): ?array
    {
        $value = $body[$key] ?? null;
        if (!is_array($value)) {
            return null;
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                return null;
            }
            $strings[] = $item;
        }

        return $strings;
    }
}
