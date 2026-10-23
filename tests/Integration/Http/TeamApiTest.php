<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class TeamApiTest extends TestCase
{
    private TenancyTestApp $app;
    private string $adminToken;
    private string $workspaceId;
    private string $workflowId;
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
        $this->workflowId = $this->createWorkflow('Standard', 'standard');
        $this->issueTypeId = $this->createIssueType('Task', 'task');
    }

    public function testCreatesTeamWithRolesAndProfiles(): void
    {
        $team = $this->createTeam('Delivery');
        $role = $this->createRole($team, 'Coder', 'Implements the planned work.', true, false);
        $profile = $this->createProfile($team, 'Skeptic', 'Constant doubt.', 'Cautious', 'Never', ['risk' => 'high']);

        $teams = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/teams',
            [],
            $this->adminToken,
        )));
        self::assertSame([$team], array_column($teams['teams'], 'id'));

        $roles = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->rolePath($team),
            [],
            $this->adminToken,
        )));
        self::assertSame([$role], array_column($roles['roles'], 'id'));
        self::assertTrue($roles['roles'][0]['unblock']);
        self::assertFalse($roles['roles'][0]['interactive']);

        $profiles = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->profilePath($team),
            [],
            $this->adminToken,
        )));
        self::assertSame([$profile], array_column($profiles['profiles'], 'id'));
        self::assertSame(['risk' => 'high'], $profiles['profiles'][0]['advanced']);

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/roles/' . $role,
            ['name' => 'Builder', 'unblock' => false, 'interactive' => true],
            $this->adminToken,
        )));
        self::assertSame('Builder', $updated['role']['name']);
        self::assertFalse($updated['role']['unblock']);
        self::assertTrue($updated['role']['interactive']);
        self::assertSame('Implements the planned work.', $updated['role']['description']);

        $updatedProfile = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/profiles/' . $profile,
            ['tone' => 'Inquisitive', 'advanced' => []],
            $this->adminToken,
        )));
        self::assertSame('Inquisitive', $updatedProfile['profile']['tone']);
        self::assertSame([], $updatedProfile['profile']['advanced']);
    }

    public function testDeletesEmptyTeamAndRejectsTeamWithChildren(): void
    {
        $team = $this->createTeam('Delivery');
        $role = $this->createRole($team, 'Coder', '', false, false);

        $blocked = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/teams/' . $team,
            [],
            $this->adminToken,
        ));
        self::assertSame(409, $blocked->getStatusCode());

        $this->app->json($this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/roles/' . $role,
            [],
            $this->adminToken,
        )));

        $deleted = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/teams/' . $team,
            [],
            $this->adminToken,
        ));
        self::assertSame(200, $deleted->getStatusCode());
        self::assertSame('team not found', $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/teams/' . $team,
            [],
            $this->adminToken,
        )), 404)['error']);
    }

    public function testStatusOwnsRolesAndRejectsForeignRoles(): void
    {
        $team = $this->createTeam('Delivery');
        $coder = $this->createRole($team, 'Coder', '', false, false);
        $reviewer = $this->createRole($team, 'Reviewer', '', false, false);

        $status = $this->createStatus('Refining', 'initial', [$coder, $reviewer]);
        $read = $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/statuses/' . $status,
            [],
            $this->adminToken,
        )));
        self::assertEqualsCanonicalizing([$coder, $reviewer], $read['status']['roleIds']);

        $cleared = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/statuses/' . $status,
            ['roleIds' => []],
            $this->adminToken,
        )));
        self::assertSame([], $cleared['status']['roleIds']);

        $otherWorkspace = $this->createWorkspace('Beta', 'BET');
        $otherTeam = $this->createTeam('Other', $otherWorkspace);
        $foreignRole = $this->createRole($otherTeam, 'Outsider', '', false, false, $otherWorkspace);

        $response = $this->app->handle($this->app->request(
            'PATCH',
            '/api/workspaces/' . $this->workspaceId . '/statuses/' . $status,
            ['roleIds' => [$foreignRole]],
            $this->adminToken,
        ));
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('roleIds', $this->app->json($response, 422)['field']);
    }

    public function testRoleAssignedToStatusCannotBeDeleted(): void
    {
        $team = $this->createTeam('Delivery');
        $coder = $this->createRole($team, 'Coder', '', false, false);
        $this->createStatus('Refining', 'initial', [$coder]);

        $response = $this->app->handle($this->app->request(
            'DELETE',
            '/api/workspaces/' . $this->workspaceId . '/roles/' . $coder,
            [],
            $this->adminToken,
        ));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testMemberCanReadButNotWriteTeams(): void
    {
        $team = $this->createTeam('Delivery');
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $read = $this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/teams/' . $team,
            [],
            $bob,
        ));
        self::assertSame(200, $read->getStatusCode());

        $write = $this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/teams',
            ['name' => 'Forbidden'],
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

    private function createTeam(string $name, ?string $workspaceId = null): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . ($workspaceId ?? $this->workspaceId) . '/teams',
            ['name' => $name],
            $this->adminToken,
        )), 201);

        return $data['team']['id'];
    }

    private function createRole(
        string $teamId,
        string $name,
        string $description,
        bool $unblock,
        bool $interactive,
        ?string $workspaceId = null,
    ): string {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . ($workspaceId ?? $this->workspaceId) . '/teams/' . $teamId . '/roles',
            [
                'name' => $name,
                'description' => $description,
                'unblock' => $unblock,
                'interactive' => $interactive,
            ],
            $this->adminToken,
        )), 201);

        return $data['role']['id'];
    }

    /**
     * @param array<string, mixed> $advanced
     */
    private function createProfile(
        string $teamId,
        string $name,
        string $style,
        string $tone,
        string $emojiUsage,
        array $advanced,
    ): string {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->profilePath($teamId),
            [
                'name' => $name,
                'style' => $style,
                'tone' => $tone,
                'emojiUsage' => $emojiUsage,
                'advanced' => $advanced,
            ],
            $this->adminToken,
        )), 201);

        return $data['profile']['id'];
    }

    /** @param list<string> $roleIds */
    private function createStatus(string $name, string $type, array $roleIds): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/workflows/' . $this->workflowId . '/statuses',
            [
                'issueTypeId' => $this->issueTypeId,
                'name' => $name,
                'type' => $type,
                'roleIds' => $roleIds,
            ],
            $this->adminToken,
        )), 201);

        return $data['status']['id'];
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

    private function rolePath(string $teamId): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/teams/' . $teamId . '/roles';
    }

    private function profilePath(string $teamId): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/teams/' . $teamId . '/profiles';
    }
}
