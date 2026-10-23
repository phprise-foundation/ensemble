<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class AgentApiTest extends TestCase
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

    public function testCreatesReadsAndUpdatesAnAgent(): void
    {
        $team = $this->createTeam('Delivery');
        $role = $this->createRole($team, 'Coder');
        $profile = $this->createProfile($team, 'Skeptic');
        $agent = $this->createAgent($team, [
            'name' => 'Kaio',
            'whoAmI' => 'A pragmatic builder.',
            'age' => 27,
            'maritalStatus' => 'Single',
            'roleIds' => [$role],
            'profileId' => $profile,
            'avatarColor' => '#FF991F',
            'model' => 'opencode/glm-5.3-flash-free',
            'fallbackModel' => 'opencode/glm-4.7',
            'interval' => 120,
            'active' => false,
        ]);

        self::assertSame('Kaio', $agent['name']);
        self::assertSame(27, $agent['age']);
        self::assertSame('single', $agent['maritalStatus']);
        self::assertSame([$role], $agent['roleIds']);
        self::assertSame($profile, $agent['profileId']);
        self::assertSame('#ff991f', $agent['avatarColor']);
        self::assertSame('opencode/glm-5.3-flash-free', $agent['model']);
        self::assertSame('opencode/glm-4.7', $agent['fallbackModel']);
        self::assertSame(120, $agent['interval']);
        self::assertFalse($agent['active']);
        self::assertSame('', $agent['portrait']);

        $listed = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->teamAgentPath($team),
            [],
            $this->adminToken,
        )));
        self::assertSame([$agent['id']], array_column($listed['agents'], 'id'));

        $read = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->agentPath($agent['id']),
            [],
            $this->adminToken,
        )));
        self::assertSame($agent['id'], $read['agent']['id']);

        $updated = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            $this->agentPath($agent['id']),
            ['name' => 'Builder', 'whoAmI' => 'A patient builder.', 'age' => null, 'maritalStatus' => 'Married', 'active' => true, 'interval' => 60],
            $this->adminToken,
        )))['agent'];
        self::assertSame('Builder', $updated['name']);
        self::assertNull($updated['age']);
        self::assertSame('married', $updated['maritalStatus']);
        self::assertTrue($updated['active']);
        self::assertSame(60, $updated['interval']);
    }

    public function testRejectsInvalidAgentPayloads(): void
    {
        $team = $this->createTeam('Delivery');
        $role = $this->createRole($team, 'Coder');

        $badMarital = $this->app->handle($this->app->request(
            'POST',
            $this->teamAgentPath($team),
            ['name' => 'Kaio', 'maritalStatus' => 'Complicated'],
            $this->adminToken,
        ));
        self::assertSame(422, $badMarital->getStatusCode());
        self::assertSame('maritalStatus', $this->app->json($badMarital, 422)['field']);

        $badColor = $this->app->handle($this->app->request(
            'POST',
            $this->teamAgentPath($team),
            ['name' => 'Kaio', 'avatarColor' => 'blue'],
            $this->adminToken,
        ));
        self::assertSame(422, $badColor->getStatusCode());
        self::assertSame('avatarColor', $this->app->json($badColor, 422)['field']);

        $badPortrait = $this->app->handle($this->app->request(
            'POST',
            $this->teamAgentPath($team),
            ['name' => 'Kaio', 'portrait' => 'data:image/gif;base64,AAAA'],
            $this->adminToken,
        ));
        self::assertSame(422, $badPortrait->getStatusCode());
        self::assertSame('portrait', $this->app->json($badPortrait, 422)['field']);

        $badInterval = $this->app->handle($this->app->request(
            'POST',
            $this->teamAgentPath($team),
            ['name' => 'Kaio', 'interval' => 5],
            $this->adminToken,
        ));
        self::assertSame(422, $badInterval->getStatusCode());
        self::assertSame('interval', $this->app->json($badInterval, 422)['field']);

        $ok = $this->createAgent($team, ['name' => 'Kaio', 'roleIds' => [$role]]);
        $agentId = $ok['id'];
        $foreignWorkspace = $this->createWorkspace('Beta', 'BET');
        $foreignTeam = $this->createTeam('Outsiders', $foreignWorkspace);
        $foreignRole = $this->createRole($foreignTeam, 'Outsider', $foreignWorkspace);

        $foreignRoleIds = $this->app->handle($this->app->request(
            'PATCH',
            $this->agentPath($agentId),
            ['roleIds' => [$foreignRole]],
            $this->adminToken,
        ));
        self::assertSame(422, $foreignRoleIds->getStatusCode());
        self::assertSame('roleIds', $this->app->json($foreignRoleIds, 422)['field']);

        $unknownTeam = $this->app->handle($this->app->request(
            'PATCH',
            $this->agentPath($agentId),
            ['teamId' => str_repeat('b', 32)],
            $this->adminToken,
        ));
        self::assertSame(404, $unknownTeam->getStatusCode());

        $badAge = $this->app->handle($this->app->request(
            'PATCH',
            $this->agentPath($agentId),
            ['age' => 400],
            $this->adminToken,
        ));
        self::assertSame(422, $badAge->getStatusCode());
        self::assertSame('age', $this->app->json($badAge, 422)['field']);
    }

    public function testAcceptsADataUrlPortraitWithinLimit(): void
    {
        $team = $this->createTeam('Delivery');
        $dataUrl = 'data:image/png;base64,' . base64_encode(str_repeat('a', 1024));
        $agent = $this->createAgent($team, ['name' => 'Vivy', 'portrait' => $dataUrl]);

        self::assertSame($dataUrl, $agent['portrait']);

        $cleared = $this->app->json($this->app->handle($this->app->request(
            'PATCH',
            $this->agentPath($agent['id']),
            ['portrait' => ''],
            $this->adminToken,
        )))['agent'];
        self::assertSame('', $cleared['portrait']);
    }

    public function testDeletesAnAgent(): void
    {
        $team = $this->createTeam('Delivery');
        $agent = $this->createAgent($team, ['name' => 'Kaio']);

        $deleted = $this->app->handle($this->app->request(
            'DELETE',
            $this->agentPath($agent['id']),
            [],
            $this->adminToken,
        ));
        self::assertSame(200, $deleted->getStatusCode());

        $read = $this->app->handle($this->app->request(
            'GET',
            $this->agentPath($agent['id']),
            [],
            $this->adminToken,
        ));
        self::assertSame(404, $read->getStatusCode());

        $listed = $this->app->json($this->app->handle($this->app->request(
            'GET',
            $this->teamAgentPath($team),
            [],
            $this->adminToken,
        )));
        self::assertSame([], $listed['agents']);
    }

    public function testMemberCanReadButNotWriteAgents(): void
    {
        $team = $this->createTeam('Delivery');
        $agentId = $this->createAgent($team, ['name' => 'Kaio'])['id'];
        $this->createUser('bob', 'bob12345', 'Bob');
        $this->addMember('bob', 'member');
        $bob = $this->app->login('bob', 'bob12345');

        $read = $this->app->handle($this->app->request(
            'GET',
            $this->agentPath($agentId),
            [],
            $bob,
        ));
        self::assertSame(200, $read->getStatusCode());

        $write = $this->app->handle($this->app->request(
            'POST',
            $this->teamAgentPath($team),
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

    private function createRole(string $teamId, string $name, ?string $workspaceId = null): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . ($workspaceId ?? $this->workspaceId) . '/teams/' . $teamId . '/roles',
            ['name' => $name],
            $this->adminToken,
        )), 201);

        return $data['role']['id'];
    }

    private function createProfile(string $teamId, string $name): string
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces/' . $this->workspaceId . '/teams/' . $teamId . '/profiles',
            ['name' => $name, 'style' => 'Constant doubt.', 'tone' => 'Cautious', 'emojiUsage' => 'Never', 'advanced' => []],
            $this->adminToken,
        )), 201);

        return $data['profile']['id'];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function createAgent(string $teamId, array $fields): array
    {
        $data = $this->app->json($this->app->handle($this->app->request(
            'POST',
            $this->teamAgentPath($teamId),
            $fields,
            $this->adminToken,
        )), 201);

        return $data['agent'];
    }

    private function createUser(string $login, string $password, string $name): void
    {
        $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/users',
            ['login' => $login, 'password' => $password, 'name' => $name],
            $this->adminToken,
        )), 201);
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

    private function teamAgentPath(string $teamId): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/teams/' . $teamId . '/agents';
    }

    private function agentPath(string $agentId): string
    {
        return '/api/workspaces/' . $this->workspaceId . '/agents/' . $agentId;
    }
}
