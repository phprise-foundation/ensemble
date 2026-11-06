<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

final class TenancyPaths
{
    public static function matches(string $path): bool
    {
        return str_starts_with($path, '/api/auth/')
            || $path === '/api/me'
            || str_starts_with($path, '/api/users')
            || str_starts_with($path, '/api/workspaces')
            || $path === '/api/models';
    }
}
