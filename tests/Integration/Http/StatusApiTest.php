<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class StatusApiTest extends TestCase
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

    public function testCreatesStatusesAndTransitions(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $refining = $this->createStatus($task, 'Refining', 'initial');
        $refined = $this->createStatus($task, 'Refined', 'transitional');
        $done = $this->createStatus($task, 'Done', 'final');

        $transition = $this->createTransition($refining, $refined, 'both', 'approved');
        $list = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->statusPath() . '?issueTypeId=' . $task,
            [],
            $this->adminToken,
        )));
        self::assertEqualsCanonicalizing([$refining, $refined, $done], array_column($list['statuses'], 'id'));

        $transitions = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->transitionPath(),
            [],
            $this->adminToken,
        )));
        self::assertSame([$transition], array_column($transitions['transitions'], 'id'));
        self::assertSame('approved', $transitions['transitions'][0]['outcome']);

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/transitions/' . $transition,
            ['actor' => 'user', 'outcome' => 'accepted'],
            $this->adminToken,
        )));
        self::assertSame('user', $updated['transition']['actor']);
        self::assertSame('accepted', $updated['transition']['outcome']);
    }

    public function testFirstStatusMustBeInitial(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $response = $this->app->handle($this->app->request(
            'POST',
            $this->statusPath(),
            ['issueTypeId' => $task, 'name' => 'Refined', 'type' => 'transitional'],
            $this->adminToken,
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('type', $this->app->json($response, 422)['field']);
    }

    public function testRejectsSecondInitial(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $this->createStatus($task, 'Refining', 'initial');

        $response = $this->app->handle($this->app->request(
            'POST',
            $this->statusPath(),
            ['issueTypeId' => $task, 'name' => 'Other initial', 'type' => 'initial'],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testTransitionCannotCrossIssueTypes(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $rfc = $this->createIssueType('RFC', 'rfc');
        $taskInitial = $this->createStatus($task, 'Refining', 'initial');
        $rfcInitial = $this->createStatus($rfc, 'Discussing', 'initial');

        $response = $this->app->handle($this->app->request(
            'POST',
            $this->transitionPath(),
            ['fromStatusId' => $taskInitial, 'toStatusId' => $rfcInitial, 'actor' => 'both'],
            $this->adminToken,
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('toStatusId', $this->app->json($response, 422)['field']);
    }

    public function testRejectsDuplicateTransition(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $from = $this->createStatus($task, 'Refining', 'initial');
        $to = $this->createStatus($task, 'Refined', 'transitional');
        $this->createTransition($from, $to, 'both', null);

        $response = $this->app->handle($this->app->request(
            'POST',
            $this->transitionPath(),
            ['fromStatusId' => $from, 'toStatusId' => $to, 'actor' => 'both'],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testStatusInUseCannotBeDeleted(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $from = $this->createStatus($task, 'Refining', 'initial');
        $to = $this->createStatus($task, 'Refined', 'transitional');
        $this->createTransition($from, $to, 'both', null);

        $response = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/statuses/' . $from,
            [],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testCannotDemoteOnlyInitial(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $initial = $this->createStatus($task, 'Refining', 'initial');

        $response = $this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/statuses/' . $initial,
            ['type' => 'transitional'],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testMemberCanReadButNotWrite(): void
    {
        $task = $this->createIssueType('Task', 'task');
        $this->createStatus($task, 'Refining', 'initial');
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $read = $this->app->handle($this->app->request('GET', $this->statusPath(), [], $bob));
        self::assertSame(200, $read->getStatusCode());

        $write = $this->app->handle($this->app->request(
            'POST',
            $this->statusPath(),
            ['issueTypeId' => $task, 'name' => 'Refined', 'type' => 'transitional'],
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
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $this->workflowId . '/issue-types',
            ['name' => $name, 'key' => $key],
            $this->adminToken,
        )), 201);

        return $data['issueType']['id'];
    }

    private function createStatus(string $issueTypeId, string $name, string $type): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->statusPath(),
            ['issueTypeId' => $issueTypeId, 'name' => $name, 'type' => $type],
            $this->adminToken,
        )), 201);

        return $data['status']['id'];
    }

    private function createTransition(string $from, string $to, string $actor, ?string $outcome): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->transitionPath(),
            ['fromStatusId' => $from, 'toStatusId' => $to, 'actor' => $actor, 'outcome' => $outcome],
            $this->adminToken,
        )), 201);

        return $data['transition']['id'];
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

    private function statusPath(): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/workflows/' . $this->workflowId . '/statuses';
    }

    private function transitionPath(): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/workflows/' . $this->workflowId . '/transitions';
    }
}
