<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class UsageApiTest extends TestCase
{
    private TenancyTestApp $app;

    protected function setUp(): void
    {
        try {
            $this->app = new TenancyTestApp();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }
    }

    public function testNewWorkspacesStartWithOneDollarAndNoUsage(): void
    {
        $token = $this->app->login('admin', 'admin1234');
        $created = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces',
            ['name' => 'Metered', 'prefix' => 'MTR'],
            $token,
        )), 201);
        $workspaceId = $created['workspace']['id'];

        $usage = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $workspaceId . '/usage',
            [],
            $token,
        )));

        self::assertSame(1_000_000, $usage['creditMicros']);
        self::assertSame(0, $usage['chargedMicros']);
        self::assertSame(1_000_000, $usage['remainingMicros']);
        self::assertSame(0, $usage['today']['chargedMicros']);
        self::assertSame(0, $usage['month']['chargedMicros']);
    }
}
