<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SecretApiTest extends TestCase
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

    public function testCreatesListsRotatesAndDeletesWithoutLeakingTheValue(): void
    {
        $created = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->secretPath(),
            ['name' => 'github-app', 'value' => 'ghp_original'],
            $this->adminToken,
        )), 201);
        $secret = $created['secret'];
        self::assertSame('github-app', $secret['name']);
        self::assertArrayNotHasKey('value', $secret);
        self::assertArrayNotHasKey('ciphertext', $secret);

        $list = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->secretPath(),
            [],
            $this->adminToken,
        )));
        self::assertSame([$secret['id']], array_column($list['secrets'], 'id'));

        $rotated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            $this->secretPath() . '/' . $secret['id'],
            ['value' => 'ghp_rotated'],
            $this->adminToken,
        )));
        self::assertSame($secret['id'], $rotated['secret']['id']);
        self::assertArrayNotHasKey('value', $rotated['secret']);

        $deleted = $this->app->handle($this->app->request(
            'DELETE',
            $this->secretPath() . '/' . $secret['id'],
            [],
            $this->adminToken,
        ));
        self::assertSame(200, $deleted->getStatusCode());
    }

    public function testRejectsDuplicateNameAndEmptyValue(): void
    {
        $this->createSecret($this->workspaceId, 'github-app', 'ghp_original');

        $duplicate = $this->app->handle($this->app->request(
            'POST',
            $this->secretPath(),
            ['name' => 'github-app', 'value' => 'other'],
            $this->adminToken,
        ));
        self::assertSame(409, $duplicate->getStatusCode());

        $empty = $this->app->handle($this->app->request(
            'POST',
            $this->secretPath(),
            ['name' => 'empty', 'value' => ''],
            $this->adminToken,
        ));
        self::assertSame(422, $empty->getStatusCode());
        self::assertSame('value', $this->app->json($empty, 422)['field']);
    }

    public function testSecretInUseByRepositoryCannotBeDeleted(): void
    {
        $secret = $this->createSecret($this->workspaceId, 'github-app', 'ghp_original');
        $repository = $this->createRepository($secret);

        $blocked = $this->app->handle($this->app->request(
            'DELETE',
            $this->secretPath() . '/' . $secret,
            [],
            $this->adminToken,
        ));
        self::assertSame(409, $blocked->getStatusCode());

        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/repositories/' . $repository,
            [],
            $this->adminToken,
        )));

        $deleted = $this->app->handle($this->app->request(
            'DELETE',
            $this->secretPath() . '/' . $secret,
            [],
            $this->adminToken,
        ));
        self::assertSame(200, $deleted->getStatusCode());
    }

    public function testMemberCanReadButNotWrite(): void
    {
        $this->createSecret($this->workspaceId, 'github-app', 'ghp_original');
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $read = $this->app->handle($this->app->request('GET', $this->secretPath(), [], $bob));
        self::assertSame(200, $read->getStatusCode());

        $write = $this->app->handle($this->app->request(
            'POST',
            $this->secretPath(),
            ['name' => 'forbidden', 'value' => 'x'],
            $bob,
        ));
        self::assertSame(403, $write->getStatusCode());
    }

    public function testSecretFromAnotherWorkspaceIsNotFound(): void
    {
        $otherWorkspace = $this->createWorkspace('Beta', 'BET');
        $foreign = $this->createSecret($otherWorkspace, 'github-app', 'ghp_foreign');

        $response = $this->app->handle($this->app->request(
            'GET',
            $this->secretPath() . '/' . $foreign,
            [],
            $this->adminToken,
        ));

        self::assertSame(404, $response->getStatusCode());
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

    private function createRepository(string $secretId): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/repositories',
            [
                'name' => 'App',
                'provider' => 'github',
                'url' => 'https://github.com/acme/app.git',
                'baseBranch' => 'main',
                'secretId' => $secretId,
            ],
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

    private function secretPath(): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/secrets';
    }
}
