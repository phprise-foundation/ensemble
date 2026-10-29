<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class RepositoryApiTest extends TestCase
{
    private TenancyTestApp $app;
    private string $adminToken;
    private string $workspaceId;

    protected function setUp(): void
    {
        try {
            $this->app = new TenancyTestApp();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }
        $this->adminToken = $this->app->login('admin', 'admin1234');
        $this->workspaceId = $this->createWorkspace('Alpha', 'ALP');
    }

    public function testCreatesReadsUpdatesAndDeletesRepository(): void
    {
        $secret = $this->createSecret($this->workspaceId, 'github-app', 'ghp_original');
        $created = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            [
                'name' => 'App',
                'provider' => 'github',
                'url' => 'https://github.com/acme/app.git',
                'baseBranch' => 'main',
                'secretId' => $secret,
            ],
            $this->adminToken,
        )), 201);
        $repository = $created['repository'];
        self::assertSame('github', $repository['provider']);
        self::assertSame('main', $repository['baseBranch']);
        self::assertSame($secret, $repository['secretId']);

        $read = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->repositoryPath() . '/' . $repository['id'],
            [],
            $this->adminToken,
        )));
        self::assertSame('App', $read['repository']['name']);

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            $this->repositoryPath() . '/' . $repository['id'],
            ['name' => 'App monorepo', 'baseBranch' => 'develop', 'secretId' => null],
            $this->adminToken,
        )));
        self::assertSame('App monorepo', $updated['repository']['name']);
        self::assertSame('develop', $updated['repository']['baseBranch']);
        self::assertNull($updated['repository']['secretId']);

        $deleted = $this->app->handle($this->app->request(
            'DELETE',
            $this->repositoryPath() . '/' . $repository['id'],
            [],
            $this->adminToken,
        ));
        self::assertSame(200, $deleted->getStatusCode());
    }

    public function testRejectsInvalidProviderUrlAndBranch(): void
    {
        $provider = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            ['name' => 'App', 'provider' => 'svn', 'url' => 'https://github.com/acme/app.git'],
            $this->adminToken,
        ));
        self::assertSame(422, $provider->getStatusCode());
        self::assertSame('provider', $this->app->json($provider, 422)['field']);

        $url = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            ['name' => 'App', 'provider' => 'github', 'url' => 'http://github.com/acme/app.git'],
            $this->adminToken,
        ));
        self::assertSame(422, $url->getStatusCode());
        self::assertSame('url', $this->app->json($url, 422)['field']);

        $branch = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            ['name' => 'App', 'provider' => 'github', 'url' => 'https://github.com/acme/app.git', 'baseBranch' => '..'],
            $this->adminToken,
        ));
        self::assertSame(422, $branch->getStatusCode());
        self::assertSame('baseBranch', $this->app->json($branch, 422)['field']);
    }

    public function testRejectsDuplicateName(): void
    {
        $this->createRepository('App');
        $response = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            ['name' => 'App', 'provider' => 'github', 'url' => 'https://github.com/acme/other.git'],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testSecretMustBelongToTheWorkspace(): void
    {
        $otherWorkspace = $this->createWorkspace('Beta', 'BET');
        $foreign = $this->createSecret($otherWorkspace, 'github-app', 'ghp_foreign');

        $response = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            [
                'name' => 'App',
                'provider' => 'github',
                'url' => 'https://github.com/acme/app.git',
                'secretId' => $foreign,
            ],
            $this->adminToken,
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testProbeValidatesTheDraftAndReportsAnUnreachableRepository(): void
    {
        $invalidUrl = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath() . '/test',
            ['provider' => 'github', 'url' => 'git@github.com:acme/app.git'],
            $this->adminToken,
        ));
        self::assertSame(422, $invalidUrl->getStatusCode());
        self::assertSame('url', $this->app->json($invalidUrl, 422)['field']);

        $unknownSecret = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath() . '/test',
            [
                'provider' => 'github',
                'url' => 'https://github.com/acme/app.git',
                'secretId' => '00000000000000000000000000000000',
            ],
            $this->adminToken,
        ));
        self::assertSame(422, $unknownSecret->getStatusCode());
        self::assertSame('secretId', $this->app->json($unknownSecret, 422)['field']);

        $unreachable = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath() . '/test',
            ['provider' => 'github', 'url' => 'https://127.0.0.1:1/acme/app.git'],
            $this->adminToken,
        )));
        self::assertFalse($unreachable['ok']);
        self::assertStringContainsString('could not reach the repository', $unreachable['message']);
    }

    public function testMemberCanReadButNotWrite(): void
    {
        $this->createRepository('App');
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $read = $this->app->handle($this->app->request('GET', $this->repositoryPath(), [], $bob));
        self::assertSame(200, $read->getStatusCode());

        $write = $this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            ['name' => 'Forbidden', 'provider' => 'github', 'url' => 'https://github.com/acme/other.git'],
            $bob,
        ));
        self::assertSame(403, $write->getStatusCode());
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

    private function createSecret(string $workspaceId, string $name, string $value): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $workspaceId . '/secrets',
            ['name' => $name, 'value' => $value],
            $this->adminToken,
        )), 201);

        return $data['secret']['id'];
    }

    private function createRepository(string $name): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->repositoryPath(),
            ['name' => $name, 'provider' => 'github', 'url' => 'https://github.com/acme/app.git'],
            $this->adminToken,
        )), 201);

        return $data['repository']['id'];
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

    private function addMember(string $login, string $role): void
    {
        $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/members',
            ['login' => $login, 'role' => $role],
            $this->adminToken,
        )));
    }

    private function repositoryPath(): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/repositories';
    }
}
