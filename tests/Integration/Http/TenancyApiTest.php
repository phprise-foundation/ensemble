<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class TenancyApiTest extends TestCase
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

    public function testInstallsAdminAndWorkspace(): void
    {
        $data = $this->app->json($this->app->handle(
            $this->app->request('GET', '/api/me', [], $this->adminToken),
        ));

        self::assertSame('admin', $data['user']['login']);
        self::assertTrue($data['user']['isAdmin']);
        self::assertCount(1, $data['workspaces']);
        self::assertSame('KAN', $data['workspaces'][0]['prefix']);
        self::assertSame('owner', $data['workspaces'][0]['role']);
    }

    public function testAdminCreatesUserAndUserCannotListUsers(): void
    {
        $this->createUser('bob', 'bob12345', 'Bob');
        $bob = $this->app->login('bob', 'bob12345');

        $response = $this->app->handle($this->app->request('GET', '/api/users', [], $bob));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUserOnlySeesTheirWorkspaces(): void
    {
        $bob = $this->createUser('bob', 'bob12345', 'Bob');
        $alpha = $this->createWorkspace('Alpha', 'ALP');
        $beta = $this->createWorkspace('Beta', 'BET');
        $this->addMember($alpha, 'bob', 'member');

        $list = $this->app->json($this->app->handle(
            $this->app->request('GET', '/api/workspaces', [], $this->app->login('bob', 'bob12345')),
        ));
        self::assertSame([$alpha], array_column($list['workspaces'], 'id'));

        $forbidden = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $beta,
            [],
            $this->app->login('bob', 'bob12345'),
        ));
        self::assertSame(404, $forbidden->getStatusCode());

        $members = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $beta . '/members',
            [],
            $this->app->login('bob', 'bob12345'),
        ));
        self::assertSame(404, $members->getStatusCode());
    }

    public function testManagerAddsAndRemovesMembers(): void
    {
        $alpha = $this->createWorkspace('Alpha', 'ALP');
        $bob = $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember($alpha, 'bob', 'member');
        $bobToken = $this->app->login('bob', 'bob12345');

        $denied = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $alpha . '/members',
            ['login' => 'admin', 'role' => 'member'],
            $bobToken,
        ));
        self::assertSame(403, $denied->getStatusCode());

        $removed = $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $alpha . '/members/' . $bob,
            [],
            $this->adminToken,
        )));
        self::assertTrue($removed['deleted']);

        $after = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $alpha,
            [],
            $bobToken,
        ));
        self::assertSame(404, $after->getStatusCode());
    }

    public function testLastOwnerCannotBeRemoved(): void
    {
        $alpha = $this->createWorkspace('Alpha', 'ALP');
        $me = $this->app->json($this->app->handle(
            $this->app->request('GET', '/api/me', [], $this->adminToken),
        ));
        $adminId = $me['user']['id'];

        $response = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $alpha . '/members/' . $adminId,
            [],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testUpdatesWorkspace(): void
    {
        $alpha = $this->createWorkspace('Alpha', 'ALP');

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $alpha,
            ['name' => 'Alpha Renamed', 'prefix' => 'alp2'],
            $this->adminToken,
        )));

        self::assertSame('Alpha Renamed', $updated['workspace']['name']);
        self::assertSame('ALP2', $updated['workspace']['prefix']);
    }

    public function testRejectsInvalidPrefix(): void
    {
        $response = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces',
            ['name' => 'Alpha', 'prefix' => '1'],
            $this->adminToken,
        ));

        self::assertSame(422, $response->getStatusCode());
        $data = $this->app->json($response, 422);
        self::assertSame('prefix', $data['field']);
    }

    public function testRejectsDuplicateLogin(): void
    {
        $this->createUser('bob', 'bob12345', 'Bob');

        $response = $this->app->handle($this->app->request(
            'POST',
            '/api/users',
            ['login' => 'bob', 'password' => 'bob12345'],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testLogoutInvalidatesSession(): void
    {
        $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/auth/logout',
            [],
            $this->adminToken,
        )));

        $response = $this->app->handle($this->app->request('GET', '/api/me', [], $this->adminToken));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testRejectsMissingToken(): void
    {
        $response = $this->app->handle($this->app->request('GET', '/api/me'));

        self::assertSame(401, $response->getStatusCode());
    }

    private function createUser(string $login, string $password, string $displayName): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/users',
            ['login' => $login, 'password' => $password, 'displayName' => $displayName],
            $this->adminToken,
        )), 201);

        return $data['user']['id'];
    }

    private function createWorkspace(string $name, string $prefix): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces',
            ['name' => $name, 'prefix' => $prefix],
            $this->adminToken,
        )), 201);

        return $data['workspace']['id'];
    }

    private function addMember(string $workspaceId, string $login, string $role): void
    {
        $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $workspaceId . '/members',
            ['login' => $login, 'role' => $role],
            $this->adminToken,
        )));
    }
}
