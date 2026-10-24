<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class IssueApiTest extends TestCase
{
    private TenancyTestApp $app;
    private string $adminToken;
    private string $workspaceId;
    private string $workflowId;
    private string $taskTypeId;
    private string $refiningId;
    private string $refinedId;
    private string $doneId;

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
        $this->taskTypeId = $this->createIssueType('Task', 'task');
        $this->refiningId = $this->createStatus($this->taskTypeId, 'Refining', 'initial');
        $this->refinedId = $this->createStatus($this->taskTypeId, 'Refined', 'transitional');
        $this->doneId = $this->createStatus($this->taskTypeId, 'Done', 'final');
        $this->createTransition($this->refiningId, $this->refinedId);
        $this->createTransition($this->refinedId, $this->doneId);
    }

    public function testCreatesListsUpdatesCommentsAndMovesIssue(): void
    {
        $priority = $this->createField($this->taskTypeId, [
            'label' => 'Priority',
            'key' => 'priority',
            'type' => 'single_select',
            'options' => ['Low', 'High'],
            'required' => true,
        ]);

        $first = $this->createIssue(['summary' => 'First issue', 'fields' => ['priority' => 'High']]);
        $second = $this->createIssue(['summary' => 'Second issue', 'fields' => ['priority' => 'Low']]);
        self::assertSame('ALP-1', $first);
        self::assertSame('ALP-2', $second);

        $list = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            [],
            $this->adminToken,
        )));
        self::assertSame([$first, $second], array_column($list['issues'], 'key'));

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $first,
            ['summary' => 'First issue v2', 'fields' => ['priority' => 'Low']],
            $this->adminToken,
        )));
        self::assertSame('First issue v2', $updated['issue']['summary']);
        self::assertSame(['priority' => 'Low'], $updated['issue']['fields']);

        $comment = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $first . '/comments',
            ['body' => 'Looking into it.'],
            $this->adminToken,
        )), 201);
        self::assertSame('admin', $comment['comment']['author']);

        $moved = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $first . '/move',
            ['statusId' => $this->refinedId],
            $this->adminToken,
        )));
        self::assertSame($this->refinedId, $moved['issue']['statusId']);

        $detail = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $first,
            [],
            $this->adminToken,
        )));
        self::assertCount(1, $detail['comments']);
        self::assertCount(2, $detail['history']);
        self::assertNull($detail['history'][0]['fromStatusId']);
        self::assertSame($this->refinedId, $detail['history'][1]['toStatusId']);

        $invalid = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $first . '/move',
            ['statusId' => $this->refiningId],
            $this->adminToken,
        ));
        self::assertSame(409, $invalid->getStatusCode());
    }

    public function testRequiredAndChoiceFieldsAreValidated(): void
    {
        $this->createField($this->taskTypeId, [
            'label' => 'Priority',
            'key' => 'priority',
            'type' => 'single_select',
            'options' => ['Low', 'High'],
            'required' => true,
        ]);

        $missing = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            ['issueTypeId' => $this->taskTypeId, 'summary' => 'No priority'],
            $this->adminToken,
        ));
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame('fields.priority', $this->app->json($missing, 422)['field']);

        $invalid = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            ['issueTypeId' => $this->taskTypeId, 'summary' => 'Bad priority', 'fields' => ['priority' => 'Urgent']],
            $this->adminToken,
        ));
        self::assertSame(422, $invalid->getStatusCode());
    }

    public function testBoardFiltersIssuesByColumnsAndType(): void
    {
        $rfc = $this->createIssueType('RFC', 'rfc');
        $rfcInitial = $this->createStatus($rfc, 'Discussing', 'initial');
        $task = $this->createIssue(['summary' => 'Task issue']);
        $rfcIssue = $this->createIssue(['issueTypeId' => $rfc, 'statusId' => $rfcInitial, 'summary' => 'RFC issue']);

        $board = $this->createBoard([$this->taskTypeId], [$this->refiningId]);
        $this->createColumn($board, 'Refining', [$this->refiningId]);

        $all = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/issues?boardId=' . $board,
            [],
            $this->adminToken,
        )));
        self::assertSame([$task], array_column($all['issues'], 'key'));
        self::assertNotContains($rfcIssue, array_column($all['issues'], 'key'));
    }

    public function testMemberCanOperateIssues(): void
    {
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $issue = $this->createIssue(['summary' => 'Member issue']);
        $response = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues/' . $issue . '/comments',
            ['body' => 'Member comment'],
            $bob,
        ));

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCrossWorkspaceIssueIsNotFound(): void
    {
        $other = $this->createWorkspace('Beta', 'BET');
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $response = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $other . '/issues/ALP-1',
            [],
            $bob,
        ));
        self::assertSame(404, $response->getStatusCode());
    }

    /** @param array<string, mixed> $overrides */
    private function createIssue(array $overrides): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            $overrides + [
                'issueTypeId' => $this->taskTypeId,
                'summary' => 'Issue',
                'description' => 'Description',
                'fields' => [],
            ],
            $this->adminToken,
        )), 201);

        return $data['issue']['key'];
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

    public function testIssueLevelsChainDownAndStopAtTheDeepest(): void
    {
        $l2TypeId = $this->createIssueType('Sub-issue', 'sub-issue', 2);
        $this->createStatus($l2TypeId, 'Refining', 'initial');
        $l3TypeId = $this->createIssueType('Detail', 'detail', 3);
        $this->createStatus($l3TypeId, 'Refining', 'initial');

        $l1 = $this->createIssue(['summary' => 'Level 1']);
        $l2 = $this->createIssue(['summary' => 'Level 2', 'issueTypeId' => $l2TypeId, 'parentKey' => $l1]);
        $l3 = $this->createIssue(['summary' => 'Level 3', 'issueTypeId' => $l3TypeId, 'parentKey' => $l2]);
        self::assertSame('ALP-1', $l1);
        self::assertSame('ALP-2', $l2);
        self::assertSame('ALP-3', $l3);

        $sameLevel = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            ['issueTypeId' => $l2TypeId, 'summary' => 'Wrong depth', 'parentKey' => $l2, 'fields' => []],
            $this->adminToken,
        ));
        self::assertSame(422, $sameLevel->getStatusCode());

        $frontier = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            ['issueTypeId' => $l3TypeId, 'summary' => 'Too deep', 'parentKey' => $l3, 'fields' => []],
            $this->adminToken,
        ));
        self::assertSame(422, $frontier->getStatusCode());

        $skipped = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issues',
            ['issueTypeId' => $l3TypeId, 'summary' => 'Skipping levels', 'parentKey' => $l1, 'fields' => []],
            $this->adminToken,
        ));
        self::assertSame(422, $skipped->getStatusCode());
    }

    private function createIssueType(string $name, string $key, int $level = 1): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $this->workflowId . '/issue-types',
            ['name' => $name, 'key' => $key, 'level' => $level],
            $this->adminToken,
        )), 201);

        return $data['issueType']['id'];
    }

    private function createStatus(string $issueTypeId, string $name, string $type): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $this->workflowId . '/statuses',
            ['issueTypeId' => $issueTypeId, 'name' => $name, 'type' => $type],
            $this->adminToken,
        )), 201);

        return $data['status']['id'];
    }

    private function createTransition(string $from, string $to): void
    {
        $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $this->workflowId . '/transitions',
            ['fromStatusId' => $from, 'toStatusId' => $to, 'actor' => 'both'],
            $this->adminToken,
        )), 201);
    }

    /** @param array<string, mixed> $body */
    private function createField(string $issueTypeId, array $body): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/issue-types/' . $issueTypeId . '/fields',
            $body,
            $this->adminToken,
        )), 201);

        return $data['field']['id'];
    }

    /** @param list<string> $issueTypes @param list<string> $statuses */
    private function createBoard(array $issueTypes, array $statuses): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards',
            [
                'workflowId' => $this->workflowId,
                'name' => 'Kanban',
                'filterIssueTypeIds' => $issueTypes,
                'filterStatusIds' => $statuses,
            ],
            $this->adminToken,
        )), 201);

        return $data['board']['id'];
    }

    /** @param list<string> $statuses */
    private function createColumn(string $boardId, string $name, array $statuses): void
    {
        $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/boards/' . $boardId . '/columns',
            ['name' => $name, 'statusIds' => $statuses],
            $this->adminToken,
        )), 201);
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
