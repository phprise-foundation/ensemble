<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class IssueWorkdirApiTest extends TestCase
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
        $this->workspaceId = $this->createWorkspace('Alpha', 'ALP');
        $workflowId = $this->createWorkflow('Standard', 'standard');
        $this->issueTypeId = $this->createIssueType($workflowId, 'Task', 'task');
        $this->createStatus($workflowId, 'Refining', 'initial');
    }

    public function testIssueWithoutRepositoryStartsWithoutOne(): void
    {
        $issue = $this->createIssue();

        self::assertNull($issue['repositoryId']);
        self::assertNull($issue['parentKey']);
    }

    public function testIssueCreatedWithRepositoryIsBound(): void
    {
        $repository = $this->createRepository('App');
        $issue = $this->createIssue(repositoryId: $repository);

        self::assertSame($repository, $issue['repositoryId']);
    }

    public function testChildInheritsTheParentWorkdir(): void
    {
        $parent = $this->createIssue();
        $child = $this->createIssue(parentKey: $parent['key']);
        self::assertSame($parent['key'], $child['parentKey']);
        self::assertNull($child['repositoryId']);

        $repository = $this->createRepository('App');
        $binding = $this->attach($child['key'], $repository);
        self::assertSame($repository, $binding['repositoryId']);
        self::assertSame('pending', $binding['state']);

        $parentAfter = $this->readIssue($parent['key']);
        self::assertSame($repository, $parentAfter['repositoryId']);
    }

    public function testAttachIsIdempotentAndConflictsOnAnotherRepository(): void
    {
        $issue = $this->createIssue();
        $first = $this->createRepository('App');
        $second = $this->createRepository('Other');

        $binding = $this->attach($issue['key'], $first);
        self::assertSame($first, $binding['repositoryId']);
        self::assertSame('agent/' . $issue['key'], $binding['branch']);

        $again = $this->attach($issue['key'], $first);
        self::assertSame($first, $again['repositoryId']);

        $conflict = $this->app->handle($this->app->request(
            'POST',
            $this->attachPath($issue['key']),
            ['repositoryId' => $second],
            $this->adminToken,
        ));
        self::assertSame(409, $conflict->getStatusCode());
    }

    public function testAttachRequiresTheRepositoryInTheWorkspace(): void
    {
        $issue = $this->createIssue();
        $otherWorkspace = $this->createWorkspace('Beta', 'BET');
        $foreign = $this->createRepositoryIn($otherWorkspace, 'App');

        $foreignResponse = $this->app->handle($this->app->request(
            'POST',
            $this->attachPath($issue['key']),
            ['repositoryId' => $foreign],
            $this->adminToken,
        ));
        self::assertSame(404, $foreignResponse->getStatusCode());

        $missing = $this->app->handle($this->app->request(
            'POST',
            $this->attachPath('ALP-999'),
            ['repositoryId' => $this->createRepository('App')],
            $this->adminToken,
        ));
        self::assertSame(404, $missing->getStatusCode());
    }

    public function testRejectsUnknownParentKey(): void
    {
        $response = $this->app->handle($this->app->request(
            'POST',
            $this->issuePath(),
            ['issueTypeId' => $this->issueTypeId, 'summary' => 'Orphan', 'description' => 'x', 'parentKey' => 'ALP-999'],
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

    private function createRepositoryIn(string $workspaceId, string $name): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $workspaceId . '/repositories',
            ['name' => $name, 'provider' => 'github', 'url' => 'https://github.com/acme/app.git'],
            $this->adminToken,
        )), 201);

        return $data['repository']['id'];
    }

    private function createRepository(string $name): string
    {
        return $this->createRepositoryIn($this->workspaceId, $name);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function createIssue(?string $parentKey = null, ?string $repositoryId = null): array
    {
        $body = array_filter([
            'issueTypeId' => $this->issueTypeId,
            'summary' => 'Framed work',
            'description' => 'The work to be done.',
            'parentKey' => $parentKey,
            'repositoryId' => $repositoryId,
        ], static fn(mixed $value): bool => $value !== null);

        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->issuePath(),
            $body,
            $this->adminToken,
        )), 201);

        return $data['issue'];
    }

    private function attach(string $key, string $repositoryId): array
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->attachPath($key),
            ['repositoryId' => $repositoryId],
            $this->adminToken,
        )));

        return $data['binding'];
    }

    /** @return array<string, mixed> */
    private function readIssue(string $key): array
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $key,
            [],
            $this->adminToken,
        )));

        return $data['issue'];
    }

    private function issuePath(): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/issues';
    }

    private function attachPath(string $key): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/issues/' . $key . '/repository';
    }
}
