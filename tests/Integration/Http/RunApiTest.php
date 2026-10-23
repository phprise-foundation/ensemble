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

    private function createIssue(): array
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            [
                'issueTypeId' => $this->issueTypeId,
                'summary' => 'Run me',
                'description' => 'The agent should run me.',
            ],
            $this->adminToken,
        )), 201);

        return $data['issue'];
    }
}
