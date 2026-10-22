<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class WorkflowApiTest extends TestCase
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

    public function testCreatesWorkflowIssueTypeAndFields(): void
    {
        $workflow = $this->createWorkflow('Standard', 'standard');
        $issueType = $this->createIssueType($workflow, 'Task', 'task');

        $priority = $this->createField($issueType, [
            'label' => 'Priority',
            'key' => 'priority',
            'type' => 'single_select',
            'options' => ['Low', 'High'],
            'required' => true,
        ]);
        $tags = $this->createField($issueType, [
            'label' => 'Tags',
            'key' => 'tags',
            'type' => 'multi_select',
            'options' => ['backend', 'frontend'],
        ]);

        $list = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->path($workflow, 'issue-types'),
            [],
            $this->adminToken,
        )));
        self::assertSame([$issueType], array_column($list['issueTypes'], 'id'));

        $fields = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->path($issueType, 'fields', true),
            [],
            $this->adminToken,
        )));
        self::assertSame([$priority, $tags], array_column($fields['fields'], 'id'));

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/fields/' . $priority,
            ['label' => 'Urgency', 'options' => ['Low', 'Medium', 'High']],
            $this->adminToken,
        )));
        self::assertSame('Urgency', $updated['field']['label']);
        self::assertSame(['Low', 'Medium', 'High'], $updated['field']['options']);

        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/fields/' . $tags,
            [],
            $this->adminToken,
        )));
        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/fields/' . $priority,
            [],
            $this->adminToken,
        )));
        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/issue-types/' . $issueType,
            [],
            $this->adminToken,
        )));
        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflow,
            [],
            $this->adminToken,
        )));
    }

    public function testRejectsDuplicateKeys(): void
    {
        $workflow = $this->createWorkflow('Standard', 'standard');
        $this->createIssueType($workflow, 'Task', 'task');

        $duplicateWorkflow = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows',
            ['name' => 'Another', 'key' => 'standard'],
            $this->adminToken,
        ));
        self::assertSame(409, $duplicateWorkflow->getStatusCode());

        $duplicateType = $this->app->handle($this->app->request(
            'POST',
            $this->path($workflow, 'issue-types'),
            ['name' => 'Another task', 'key' => 'task'],
            $this->adminToken,
        ));
        self::assertSame(409, $duplicateType->getStatusCode());

        $issueType = $this->createIssueType($workflow, 'RFC', 'rfc');
        $this->createField($issueType, ['label' => 'Priority', 'key' => 'priority', 'type' => 'text']);
        $duplicateField = $this->app->handle($this->app->request(
            'POST',
            $this->path($issueType, 'fields', true),
            ['label' => 'Other', 'key' => 'priority', 'type' => 'text'],
            $this->adminToken,
        ));
        self::assertSame(409, $duplicateField->getStatusCode());
    }

    public function testSelectRequiresOptionsAndTextRejectsOptions(): void
    {
        $workflow = $this->createWorkflow('Standard', 'standard');
        $issueType = $this->createIssueType($workflow, 'Task', 'task');

        $missingOptions = $this->app->handle($this->app->request(
            'POST',
            $this->path($issueType, 'fields', true),
            ['label' => 'Priority', 'type' => 'single_select'],
            $this->adminToken,
        ));
        self::assertSame(422, $missingOptions->getStatusCode());
        self::assertSame('options', $this->app->json($missingOptions, 422)['field']);

        $unexpectedOptions = $this->app->handle($this->app->request(
            'POST',
            $this->path($issueType, 'fields', true),
            ['label' => 'Note', 'type' => 'text', 'options' => ['x']],
            $this->adminToken,
        ));
        self::assertSame(422, $unexpectedOptions->getStatusCode());
        self::assertSame('options', $this->app->json($unexpectedOptions, 422)['field']);
    }

    public function testMemberCanReadButNotWrite(): void
    {
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember($this->workspaceId, 'bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $read = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/workflows',
            [],
            $bob,
        ));
        self::assertSame(200, $read->getStatusCode());

        $write = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows',
            ['name' => 'Blocked'],
            $bob,
        ));
        self::assertSame(403, $write->getStatusCode());
    }

    public function testCrossWorkspaceIsNotFound(): void
    {
        $other = $this->createWorkspace('Beta', 'BET');
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember($this->workspaceId, 'bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $response = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $other . '/workflows',
            [],
            $bob,
        ));
        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeletingParentsInUseConflicts(): void
    {
        $workflow = $this->createWorkflow('Standard', 'standard');
        $issueType = $this->createIssueType($workflow, 'Task', 'task');
        $this->createField($issueType, ['label' => 'Priority', 'type' => 'text']);

        $workflowDelete = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflow,
            [],
            $this->adminToken,
        ));
        self::assertSame(409, $workflowDelete->getStatusCode());

        $issueTypeDelete = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/issue-types/' . $issueType,
            [],
            $this->adminToken,
        ));
        self::assertSame(409, $issueTypeDelete->getStatusCode());
    }

    public function testUpdatesWorkflowAndIssueType(): void
    {
        $workflow = $this->createWorkflow('Standard', 'standard');
        $issueType = $this->createIssueType($workflow, 'Task', 'task');

        $updatedWorkflow = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflow,
            ['name' => 'Standard v2'],
            $this->adminToken,
        )));
        self::assertSame('Standard v2', $updatedWorkflow['workflow']['name']);

        $updatedType = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/issue-types/' . $issueType,
            ['name' => 'Feature', 'level' => 2, 'position' => 3],
            $this->adminToken,
        )));
        self::assertSame('Feature', $updatedType['issueType']['name']);
        self::assertSame(2, $updatedType['issueType']['level']);
        self::assertSame(3, $updatedType['issueType']['position']);
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
            $this->path($workflowId, 'issue-types'),
            ['name' => $name, 'key' => $key],
            $this->adminToken,
        )), 201);

        return $data['issueType']['id'];
    }

    /** @param array<array-key, mixed> $body */
    private function createField(string $issueTypeId, array $body): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->path($issueTypeId, 'fields', true),
            $body,
            $this->adminToken,
        )), 201);

        return $data['field']['id'];
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

    private function addMember(string $workspaceId, string $login, string $role): void
    {
        $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $workspaceId . '/members',
            ['login' => $login, 'role' => $role],
            $this->adminToken,
        )));
    }

    private function path(string $id, string $suffix, bool $issueType = false): string
    {
        $resource = $issueType ? 'issue-types' : 'workflows';

        return '/api/workspaces/' . $this->workspaceId . '/' . $resource . '/' . $id . '/' . $suffix;
    }
}
