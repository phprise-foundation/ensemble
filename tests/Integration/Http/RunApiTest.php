<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class RunApiTest extends TestCase
{
    private TenancyTestApp $app;
    private string $adminToken;
    private string $workspaceId;
    private string $issueTypeId;

    protected function setUp(): void
    {
        try {
            $this->app = new TenancyTestApp();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }
        $this->adminToken = $this->app->login('admin', 'admin1234');
        $this->workspaceId = $this->createWorkspace('Runner Space', 'RUN');
        $workflowId = $this->createWorkflow('Standard', 'standard');
        $this->issueTypeId = $this->createIssueType($workflowId, 'Task', 'task');
        $this->createStatus($workflowId, 'Refining', 'initial');
    }

    public function testIssueRunHistoryStartsEmptyAndRejectsStrangers(): void
    {
        $issue = $this->createIssue();

        $runs = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $issue['key'] . '/runs',
            [],
            $this->adminToken,
        )));
        self::assertSame([], $runs['runs']);

        $outsider = $this->createWorkspace('Other Space', 'OTH');
        $response = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $outsider . '/issues/' . $issue['key'] . '/runs',
            [],
            $this->adminToken,
        ));
        self::assertSame(404, $response->getStatusCode());
    }

    public function testRunHistoryRequiresAuthentication(): void
    {
        $issue = $this->createIssue();
        $response = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $issue['key'] . '/runs',
            [],
        ));
        self::assertSame(401, $response->getStatusCode());
    }

    public function testRequestsARunForTheAssignee(): void
    {
        $agent = $this->createAgent($this->createTeam('Runner'), ['name' => 'Kaio']);
        $issue = $this->createIssue($agent['id']);

        $requested = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->runsPath($issue['key']),
            [],
            $this->adminToken,
        )), 201);
        self::assertSame('claimed', $requested['run']['state']);
        self::assertSame($agent['id'], $requested['run']['agentId']);
        self::assertNull($requested['run']['prompt']);

        $history = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->runsPath($issue['key']),
            [],
            $this->adminToken,
        )));
        self::assertCount(1, $history['runs']);

        $again = $this->app->handle($this->app->request(
            'POST',
            $this->runsPath($issue['key']),
            [],
            $this->adminToken,
        ));
        self::assertSame(409, $again->getStatusCode());
    }

    public function testRejectsARunRequestWhenTheIssueIsReadyToMerge(): void
    {
        $agent = $this->createAgent($this->createTeam('Runner'), ['name' => 'Kaio']);
        $issue = $this->createIssue($agent['id']);
        $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $issue['key'],
            ['readyToMerge' => true],
            $this->adminToken,
        )));

        $response = $this->app->handle($this->app->request(
            'POST',
            $this->runsPath($issue['key']),
            [],
            $this->adminToken,
        ));
        self::assertSame(409, $response->getStatusCode());
    }

    public function testRejectsARunRequestWithoutAnAssignee(): void
    {
        $issue = $this->createIssue();
        $response = $this->app->handle($this->app->request(
            'POST',
            $this->runsPath($issue['key']),
            [],
            $this->adminToken,
        ));
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('assigneeId', $this->app->json($response, 422)['field']);
    }

    public function testResolveRejectsBadStrategiesAndUnknownIssues(): void
    {
        $issue = $this->createIssue();
        $endpoint = '/api/workspaces/' . $this->workspaceId . '/issues/' . $issue['key'] . '/workdir/resolve';

        $response = $this->app->handle($this->app->request('POST', $endpoint, ['strategy' => 'yolo'], $this->adminToken));
        self::assertSame(422, $response->getStatusCode());

        $ghost = '/api/workspaces/' . $this->workspaceId . '/issues/RUN-999/workdir/resolve';
        $response = $this->app->handle($this->app->request('POST', $ghost, ['strategy' => 'merge'], $this->adminToken));
        self::assertSame(404, $response->getStatusCode());

        $response = $this->app->handle($this->app->request('POST', $endpoint, ['strategy' => 'merge'], $this->adminToken));
        self::assertSame(422, $response->getStatusCode());
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

    private function createWorkflow(string $name, string $key): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows',
            ['name' => $name, 'key' => $key],
            $this->adminToken,
        )), 201);

        return $data['workflow']['id'];
    }

    private function createIssueType(string $workflowId, string $name, string $key): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflowId . '/issue-types',
            ['name' => $name, 'key' => $key],
            $this->adminToken,
        )), 201);

        return $data['issueType']['id'];
    }

    private function createStatus(string $workflowId, string $name, string $type): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflowId . '/statuses',
            ['issueTypeId' => $this->issueTypeId, 'name' => $name, 'type' => $type],
            $this->adminToken,
        )), 201);

        return $data['status']['id'];
    }

    private function createIssue(?string $assigneeId = null): array
    {
        $body = array_filter([
            'issueTypeId' => $this->issueTypeId,
            'summary' => 'Run me',
            'description' => 'The agent should run me.',
            'assigneeId' => $assigneeId,
        ], static fn(mixed $value): bool => $value !== null);

        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            $body,
            $this->adminToken,
        )), 201);

        return $data['issue'];
    }

    private function createTeam(string $name): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/teams',
            ['name' => $name],
            $this->adminToken,
        )), 201);

        return $data['team']['id'];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function createAgent(string $teamId, array $fields): array
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/teams/' . $teamId . '/agents',
            $fields,
            $this->adminToken,
        )), 201);

        return $data['agent'];
    }

    private function runsPath(string $key): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/issues/' . $key . '/runs';
    }
}
