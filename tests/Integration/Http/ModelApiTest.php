<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ModelApiTest extends TestCase
{
    private TenancyTestApp $app;
    private string $adminToken;

    protected function setUp(): void
    {
        try {
            $this->app = new TenancyTestApp();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }
        $this->adminToken = $this->app->login('admin', 'admin1234');
    }

    public function testListsThePublicCatalog(): void
    {
        $response = $this->app->handle($this->app->request('GET', '/api/models', [], $this->adminToken));
        $body = $this->app->json($response);

        self::assertSame('ens26.1-flash', $body['default']);
        self::assertCount(5, $body['models']);
        self::assertSame(
            ['ens26.1-lite', 'ens26.1-flash', 'ens26.1-plus', 'ens26.1-pro', 'ens26.1-max'],
            array_column($body['models'], 'id'),
        );

        $flash = $body['models'][1];
        self::assertSame('Ensemble 26.1 Flash', $flash['name']);
        self::assertEquals(0.02, $flash['pricePerMillion']['cachedInputTokens']);
        self::assertEquals(0.3, $flash['pricePerMillion']['inputTokens']);
        self::assertEquals(1.0, $flash['pricePerMillion']['outputTokens']);
        self::assertArrayNotHasKey('internal', $flash);
        self::assertArrayNotHasKey('model', $flash);
    }

    public function testRequiresAuthentication(): void
    {
        $response = $this->app->handle($this->app->request('GET', '/api/models'));

        self::assertSame(401, $response->getStatusCode());
    }
}
