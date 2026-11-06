<?php

declare(strict_types=1);

namespace App\Tests\Unit\Interface;

use App\Interface\Http\Tenancy\TenancyPaths;
use PHPUnit\Framework\TestCase;

final class TenancyPathsTest extends TestCase
{
    public function testRoutesTheTenancyApiThroughTheTenantRouter(): void
    {
        foreach ([
            '/api/auth/login',
            '/api/auth/logout',
            '/api/me',
            '/api/users',
            '/api/users/abc',
            '/api/workspaces',
            '/api/workspaces/abc/snapshot',
            '/api/models',
        ] as $path) {
            self::assertTrue(TenancyPaths::matches($path), $path);
        }
    }

    public function testLeavesTheLegacyApiToTheOldRouter(): void
    {
        foreach (['/api/items', '/api/board', '/api/agents', '/api/chat', '/api/models/extra'] as $path) {
            self::assertFalse(TenancyPaths::matches($path), $path);
        }
    }
}
