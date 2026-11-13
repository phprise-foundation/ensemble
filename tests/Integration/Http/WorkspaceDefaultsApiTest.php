<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Support\TenancyTestApp;
use PHPUnit\Framework\TestCase;
use Throwable;

final class WorkspaceDefaultsApiTest extends TestCase
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
        $created = $this->app->json($this->app->handle($this->app->request(
            'POST',
            '/api/workspaces',
            ['name' => 'Seeded', 'prefix' => 'SED'],
            $this->adminToken,
        )), 201);
        $this->workspaceId = $created['workspace']['id'];
    }

    public function testSeedsTeamRolesProfilesAgentsWorkflowsAndBoards(): void
    {
        $snapshot = $this->snapshot();

        self::assertSame('Shonen Squad', $snapshot['teams'][0]['name']);
        self::assertCount(7, $snapshot['roles']);
        self::assertCount(9, $snapshot['profiles']);
        self::assertCount(9, $snapshot['agents']);
        self::assertCount(3, $snapshot['workflows']);
        self::assertCount(6, $snapshot['issueTypes']);
        self::assertCount(45, $snapshot['statuses']);
        self::assertCount(2, $snapshot['boards']);

        $roles = array_column($snapshot['roles'], null, 'name');
        self::assertArrayHasKey('Product Owner', $roles);
        self::assertArrayHasKey('Solutions Architect', $roles);
        self::assertArrayHasKey('Systems Architect', $roles);
        self::assertArrayHasKey('Technical Architect', $roles);
        self::assertArrayHasKey('Coder', $roles);
        self::assertArrayHasKey('Quality Assurance', $roles);
        self::assertArrayHasKey('Tech Lead', $roles);

        $vivy = $this->agent($snapshot, 'Vivy');
        self::assertSame('female', $vivy['gender']);
        self::assertSame(20, $vivy['age']);
        self::assertSame('single', $vivy['maritalStatus']);
        self::assertSame('ens26.1-flash', $vivy['modelId']);
        self::assertStringStartsWith('data:image/png;base64,', $vivy['portrait']);

        $canceled = array_filter($snapshot['statuses'], static fn(array $status): bool => $status['name'] === 'Canceled');
        self::assertCount(6, $canceled);
        $any = array_filter(
            $snapshot['transitions'],
            static fn(array $transition): bool => $transition['fromStatusId'] === '*' || $transition['fromAny'] === true,
        );
        self::assertNotSame([], $any);

        $downstream = $this->board($snapshot, 'downstream');
        self::assertCount(4, $downstream['backlogStatusIds']);
        $publish = array_values(array_filter(
            $snapshot['columns'],
            static fn(array $column): bool => $column['boardId'] === $downstream['id'] && $column['name'] === 'Publish',
        ))[0];
        self::assertCount(10, $publish['statusIds']);
        $hotfixing = array_filter($snapshot['statuses'], static fn(array $status): bool => $status['name'] === 'Hotfixing');
        self::assertCount(2, $hotfixing);
    }

    /** @return array<array-key, mixed> */
    private function snapshot(): array
    {
        return $this->app->json($this->app->handle($this->app->request(
            'GET',
            '/api/workspaces/' . $this->workspaceId . '/snapshot',
            [],
            $this->adminToken,
        )));
    }

    /** @param array<array-key, mixed> $snapshot */
    private function agent(array $snapshot, string $name): array
    {
        foreach ($snapshot['agents'] as $agent) {
            if ($agent['name'] === $name) {
                return $agent;
            }
        }

        self::fail('Agent not found: ' . $name);
    }

    /** @param array<array-key, mixed> $snapshot */
    private function board(array $snapshot, string $key): array
    {
        foreach ($snapshot['boards'] as $board) {
            if ($board['key'] === $key) {
                return $board;
            }
        }

        self::fail('Board not found: ' . $key);
    }
}
