<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SnapshotApiTest extends TestCase
{
    private TenancyTestApp $app;
    private string $token;
    private string $workspaceId;

    protected function setUp(): void
    {
        try {
            $this->app = new TenancyTestApp();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }
        $this->token = $this->app->login('admin', 'admin1234');
        $workspace = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces',
            ['name' => 'Snapshot', 'prefix' => 'SNP'],
            $this->token,
        )), 201);
        $this->workspaceId = $workspace['workspace']['id'];
    }

    public function testSnapshotBootsTheBoardInOneRead(): void
    {
        $workflow = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows',
            ['name' => 'Standard', 'key' => 'standard'],
            $this->token,
        )), 201);
        $workflowId = $workflow['workflow']['id'];
        $issueType = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflowId . '/issue-types',
            ['name' => 'Task', 'key' => 'task', 'level' => 1],
            $this->token,
        )), 201);
        $status = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflowId . '/statuses',
            ['issueTypeId' => $issueType['issueType']['id'], 'name' => 'Refining', 'type' => 'initial'],
            $this->token,
        )), 201);
        $board = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards',
            ['workflowId' => $workflowId, 'name' => 'Main', 'key' => 'main'],
            $this->token,
        )), 201);
        $column = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $board['board']['id'] . '/columns',
            ['name' => 'Refining', 'statusIds' => [$status['status']['id']]],
            $this->token,
        )), 201);
        $issue = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            ['issueTypeId' => $issueType['issueType']['id'], 'summary' => 'Boot me', 'description' => '-'],
            $this->token,
        )), 201);

        $snapshot = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/snapshot',
            [],
            $this->token,
        )));

        self::assertSame($this->workspaceId, $snapshot['workspaces'][0]['id']);
        self::assertSame([$workflowId], array_column($snapshot['workflows'], 'id'));
        self::assertSame([$issueType['issueType']['id']], array_column($snapshot['issueTypes'], 'id'));
        self::assertSame([$status['status']['id']], array_column($snapshot['statuses'], 'id'));
        self::assertSame([$board['board']['id']], array_column($snapshot['boards'], 'id'));
        self::assertSame([$column['column']['id']], array_column($snapshot['columns'], 'id'));
        self::assertSame([$issue['issue']['key']], array_column($snapshot['issues'], 'key'));
    }

    public function testSnapshotRequiresMemberAndAuthentication(): void
    {
        $response = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/snapshot',
        ));
        self::assertSame(401, $response->getStatusCode());

        $this->createUser('nadia', 'nadia12345', 'Nadia');
        $strangerToken = $this->app->login('nadia', 'nadia12345');
        $response = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/snapshot',
            [],
            $strangerToken,
        ));
        self::assertSame(404, $response->getStatusCode());
    }

    private function createUser(string $login, string $password, string $displayName): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/users',
            ['login' => $login, 'password' => $password, 'displayName' => $displayName],
            $this->token,
        )), 201);

        return $data['user']['id'];
    }
}
