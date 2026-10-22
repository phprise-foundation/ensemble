<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class BoardApiTest extends TestCase
{
    private TenancyTestApp $app;
    private string $adminToken;
    private string $workspaceId;
    private string $workflowId;

    protected function setUp(): void
    {
        try {
            $this->app = new TenancyTestApp();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }
        $this->adminToken = $this->app->login('admin', 'admin1234');
        $this->workspaceId = $this->createWorkspace('Alpha', 'ALP');
        $this->workflowId = $this->createWorkflow('Standard', 'standard');
    }

    public function testCreatesBoardAndColumns(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $todo = $this->createStatus($task, 'Refining', 'initial');
        $doing = $this->createStatus($task, 'Coding', 'transitional');
        $board = $this->createBoard('Kanban', 'kanban', [$task], [$todo, $doing]);

        $first = $this->createColumn($board, 'Todo', [$todo]);
        $second = $this->createColumn($board, 'Doing', [$doing]);

        $detail = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $board,
            [],
            $this->adminToken,
        )));
        self::assertSame('Kanban', $detail['board']['name']);
        self::assertSame([$todo, $doing], $detail['board']['filters']['statusIds']);
        self::assertEqualsCanonicalizing([$first, $second], array_column($detail['columns'], 'id'));

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $board,
            ['name' => 'Board v2', 'filterIssueTypeIds' => []],
            $this->adminToken,
        )));
        self::assertSame('Board v2', $updated['board']['name']);
        self::assertSame([], $updated['board']['filters']['issueTypeIds']);

        $columnUpdate = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/columns/' . $second,
            ['name' => 'In progress', 'wip' => 4],
            $this->adminToken,
        )));
        self::assertSame('In progress', $columnUpdate['column']['name']);
        self::assertSame(4, $columnUpdate['column']['wip']);

        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/columns/' . $first,
            [],
            $this->adminToken,
        )));
        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/columns/' . $second,
            [],
            $this->adminToken,
        )));
        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $board,
            [],
            $this->adminToken,
        )));
    }

    public function testBoardFiltersMustBelongToTheBoardWorkflow(): void
    {
        $otherWorkflow = $this->createWorkflow('Other', 'other');
        $otherTask = $this->createIssueTypeIn($otherWorkflow, 'Other task', 'other-task');
        $this->createStatusIn($otherWorkflow, $otherTask, 'Other initial', 'initial');

        $response = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards',
            ['workflowId' => $this->workflowId, 'name' => 'Kanban', 'filterIssueTypeIds' => [$otherTask]],
            $this->adminToken,
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('filterIssueTypeIds', $this->app->json($response, 422)['field']);
    }

    public function testColumnStatusesMustBelongToTheBoardWorkflow(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $this->createStatus($task, 'Refining', 'initial');
        $board = $this->createBoard('Kanban', 'kanban');

        $otherWorkflow = $this->createWorkflow('Other', 'other');
        $otherTask = $this->createIssueTypeIn($otherWorkflow, 'Other task', 'other-task');
        $otherStatus = $this->createStatusIn($otherWorkflow, $otherTask, 'Other initial', 'initial');

        $response = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $board . '/columns',
            ['name' => 'Todo', 'statusIds' => [$otherStatus]],
            $this->adminToken,
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('statusIds', $this->app->json($response, 422)['field']);
    }

    public function testStatusBelongsToOnlyOneColumnPerBoard(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $status = $this->createStatus($task, 'Refining', 'initial');
        $board = $this->createBoard('Kanban', 'kanban');
        $this->createColumn($board, 'Todo', [$status]);

        $response = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $board . '/columns',
            ['name' => 'Doing', 'statusIds' => [$status]],
            $this->adminToken,
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('statusIds', $this->app->json($response, 422)['field']);
    }

    public function testBoardInUseCannotBeDeleted(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $status = $this->createStatus($task, 'Refining', 'initial');
        $board = $this->createBoard('Kanban', 'kanban');
        $this->createColumn($board, 'Todo', [$status]);

        $response = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $board,
            [],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testMemberCanReadButNotWriteBoards(): void
    {
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $read = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/boards',
            [],
            $bob,
        ));
        self::assertSame(200, $read->getStatusCode());

        $write = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards',
            ['workflowId' => $this->workflowId, 'name' => 'Blocked'],
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

    private function createIssueType(string $name, string $key): string
    {
        return $this->createIssueTypeIn($this->workflowId, $name, $key);
    }

    private function createIssueTypeIn(string $workflowId, string $name, string $key): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflowId . '/issue-types',
            ['name' => $name, 'key' => $key],
            $this->adminToken,
        )), 201);

        return $data['issueType']['id'];
    }

    private function createStatus(string $issueTypeId, string $name, string $type): string
    {
        return $this->createStatusIn($this->workflowId, $issueTypeId, $name, $type);
    }

    private function createStatusIn(string $workflowId, string $issueTypeId, string $name, string $type): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $workflowId . '/statuses',
            ['issueTypeId' => $issueTypeId, 'name' => $name, 'type' => $type],
            $this->adminToken,
        )), 201);

        return $data['status']['id'];
    }

    /** @param list<string> $issueTypeIds @param list<string> $statusIds */
    private function createBoard(string $name, string $key, array $issueTypeIds = [], array $statusIds = []): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards',
            [
                'workflowId' => $this->workflowId,
                'name' => $name,
                'key' => $key,
                'filterIssueTypeIds' => $issueTypeIds,
                'filterStatusIds' => $statusIds,
            ],
            $this->adminToken,
        )), 201);

        return $data['board']['id'];
    }

    /** @param list<string> $statusIds */
    private function createColumn(string $boardId, string $name, array $statusIds): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $boardId . '/columns',
            ['name' => $name, 'statusIds' => $statusIds],
            $this->adminToken,
        )), 201);

        return $data['column']['id'];
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
}
