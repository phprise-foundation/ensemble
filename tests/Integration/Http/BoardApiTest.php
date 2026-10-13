<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Application\AddComment;
use App\Application\AdvanceItem;
use App\Application\CloseItem;
use App\Application\CreateAgent;
use App\Application\CreateColumn;
use App\Application\CreateItem;
use App\Application\DeleteAgent;
use App\Application\DeleteColumn;
use App\Application\FindItem;
use App\Application\ListAgents;
use App\Application\ListBoardItems;
use App\Application\ListColumns;
use App\Application\ReadBoard;
use App\Application\UpdateAgent;
use App\Application\UpdateBoard;
use App\Application\UpdateColumn;
use App\Application\UpdateItem;
use App\Infrastructure\Authentication\PasswordAuthenticator;
use App\Infrastructure\Persistence\Sqlite\AgentMapper;
use App\Infrastructure\Persistence\Sqlite\ColumnMapper;
use App\Infrastructure\Persistence\Sqlite\ItemMapper;
use App\Infrastructure\Persistence\Sqlite\SqliteAgentRegistry;
use App\Infrastructure\Persistence\Sqlite\SqliteBoardSettings;
use App\Infrastructure\Persistence\Sqlite\SqliteColumnRepository;
use App\Infrastructure\Persistence\Sqlite\SqliteDatabase;
use App\Infrastructure\Persistence\Sqlite\SqliteItemEventLog;
use App\Infrastructure\Persistence\Sqlite\SqliteItemRepository;
use App\Interface\Http\AgentArrayPresenter;
use App\Interface\Http\AgentController;
use App\Interface\Http\BoardController;
use App\Interface\Http\BoardPresenter;
use App\Interface\Http\ItemArrayPresenter;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\WorkflowController;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class BoardApiTest extends TestCase
{
    private string $file;
    private BoardController $board;
    private WorkflowController $workflow;
    private AgentController $agents;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'board');
        unlink($this->file);
        $this->wire();
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testCreatesItemInFirstColumnAndTracksHistory(): void
    {
        $created = $this->json($this->board->handle($this->request('POST', '/api/items', ['title' => 'Do it'])), 201);
        self::assertSame('intent', $created['item']['status']);

        $moved = $this->json($this->board->handle($this->request('PATCH', '/api/items/1', ['status' => 'coding'])));
        self::assertSame('coding', $moved['item']['status']);

        $detail = $this->json($this->board->handle($this->request('GET', '/api/items/1')));
        self::assertCount(2, $detail['item']['history']);
        self::assertSame('coding', $detail['item']['history'][1]['to']);
        self::assertSame('intent', $detail['item']['history'][1]['from']);
    }

    public function testClosingMovesItemToFinalColumn(): void
    {
        $this->board->handle($this->request('POST', '/api/items', ['title' => 'Do it']));

        $closed = $this->json($this->board->handle($this->request('POST', '/api/items/1/close', [])));

        self::assertSame('done', $closed['item']['status']);
        self::assertTrue($closed['item']['closed']);
    }

    public function testReadsBoardWithColumns(): void
    {
        $board = $this->json($this->workflow->handle($this->request('GET', '/api/board')))['board'];

        self::assertSame('Multi-Agent', $board['name']);
        self::assertSame(48, $board['aging_hours']);
        self::assertCount(9, $board['columns']);
        self::assertTrue($board['columns'][8]['final']);
    }

    public function testManagesColumns(): void
    {
        $created = $this->json($this->workflow->handle($this->request('POST', '/api/board/columns', ['name' => 'Triage'])), 201);
        self::assertSame('triage', $created['column']['id']);

        $updated = $this->json($this->workflow->handle($this->request('PATCH', '/api/board/columns/triage', ['name' => 'Triaged'])));
        self::assertSame('Triaged', $updated['column']['name']);

        $this->json($this->workflow->handle($this->request('DELETE', '/api/board/columns/triage')));
        $board = $this->json($this->workflow->handle($this->request('GET', '/api/board')))['board'];
        self::assertCount(9, $board['columns']);
    }

    public function testDoesNotRemoveAColumnHoldingItems(): void
    {
        $this->board->handle($this->request('POST', '/api/items', ['title' => 'Do it']));

        $response = $this->workflow->handle($this->request('DELETE', '/api/board/columns/intent'));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testManagesAgents(): void
    {
        $created = $this->json($this->agents->handle($this->request('POST', '/api/agents', ['name' => 'Reviewer'])), 201);
        self::assertSame('reviewer', $created['agent']['id']);

        $updated = $this->json($this->agents->handle($this->request('PATCH', '/api/agents/reviewer', ['active' => false])));
        self::assertFalse($updated['agent']['active']);

        $this->agents->handle($this->request('DELETE', '/api/agents/reviewer'));
        $list = $this->json($this->agents->handle($this->request('GET', '/api/agents')))['agents'];
        self::assertNotContains('reviewer', array_column($list, 'id'));
    }

    public function testRejectsUnknownColumn(): void
    {
        $this->board->handle($this->request('POST', '/api/items', ['title' => 'Do it']));

        $response = $this->board->handle($this->request('PATCH', '/api/items/1', ['status' => 'nope']));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testBoardExposesRolesAndTransitions(): void
    {
        $board = $this->json($this->workflow->handle($this->request('GET', '/api/board')))['board'];
        $intent = $board['columns'][0];
        $done = $board['columns'][8];

        self::assertSame('product-owner', $intent['role']);
        self::assertSame(['feasibility'], $intent['transitions']);
        self::assertNull($done['role']);
        self::assertSame([], $done['transitions']);
    }

    public function testAdvancesItemAlongConfiguredTransition(): void
    {
        $this->board->handle($this->request('POST', '/api/items', ['title' => 'Do it']));

        $advanced = $this->json($this->board->handle($this->request('POST', '/api/items/1/advance', [])));

        self::assertSame('feasibility', $advanced['item']['status']);
        self::assertSame('solutions-architect', $advanced['item']['role']);
    }

    public function testRejectsADisallowedTransition(): void
    {
        $this->board->handle($this->request('POST', '/api/items', ['title' => 'Do it']));

        $response = $this->board->handle($this->request('POST', '/api/items/1/advance', ['to' => 'done']));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testConfiguresColumnRoleAndTransitions(): void
    {
        $updated = $this->json($this->workflow->handle($this->request('PATCH', '/api/board/columns/intent', [
            'role' => 'coder',
            'transitions' => ['design'],
        ])));

        self::assertSame('coder', $updated['column']['role']);
        self::assertSame(['design'], $updated['column']['transitions']);
    }

    public function testPersistsAgentSpec(): void
    {
        $created = $this->json($this->agents->handle($this->request('POST', '/api/agents', [
            'name' => 'Runner',
            'rule' => 'coder',
            'model' => 'deepseek/deepseek-flash',
            'interval' => 120,
        ])), 201);
        self::assertSame('coder', $created['agent']['rule']);
        self::assertSame(120, $created['agent']['interval']);

        $updated = $this->json($this->agents->handle($this->request('PATCH', '/api/agents/runner', [
            'profile' => 'skeptic',
            'active' => false,
        ])));
        self::assertSame('skeptic', $updated['agent']['profile']);
        self::assertFalse($updated['agent']['active']);
    }

    public function testRejectsMissingToken(): void
    {
        $response = $this->board->handle(new ServerRequest('GET', '/api/items'));

        self::assertSame(401, $response->getStatusCode());
    }

    private function wire(): void
    {
        $pdo = (new SqliteDatabase($this->file))->connection();
        $items = new SqliteItemRepository($pdo, new ItemMapper());
        $columns = new SqliteColumnRepository($pdo, new ColumnMapper());
        $registry = new SqliteAgentRegistry($pdo, new AgentMapper());
        $events = new SqliteItemEventLog($pdo);
        $settings = new SqliteBoardSettings($pdo);
        $auth = new PasswordAuthenticator('secret');
        $responder = new JsonResponder();

        $this->board = new BoardController(
            $auth,
            new ListBoardItems($items),
            new FindItem($items),
            new CreateItem($items, $columns, $events),
            new UpdateItem($items, $columns, $events),
            new AdvanceItem($items, $columns, $events),
            new AddComment($items),
            new CloseItem($items, $columns, $events),
            $events,
            new ItemArrayPresenter(),
            $responder,
        );
        $this->workflow = new WorkflowController(
            $auth,
            new ReadBoard($settings),
            new UpdateBoard($settings),
            new ListColumns($columns),
            new CreateColumn($columns, $registry),
            new UpdateColumn($columns, $registry),
            new DeleteColumn($columns, $items),
            new BoardPresenter(),
            $responder,
        );
        $this->agents = new AgentController(
            $auth,
            new ListAgents($registry),
            new CreateAgent($registry),
            new UpdateAgent($registry),
            new DeleteAgent($registry),
            new AgentArrayPresenter(),
            $responder,
        );
    }

    /** @param array<array-key, mixed> $body */
    private function request(string $method, string $path, array $body = []): ServerRequest
    {
        return new ServerRequest(
            $method,
            $path,
            ['Authorization' => 'Bearer secret', 'Content-Type' => 'application/json'],
            (string) json_encode($body),
        );
    }

    /** @return array<array-key, mixed> */
    private function json(ResponseInterface $response, int $expected = 200): array
    {
        self::assertSame($expected, $response->getStatusCode(), (string) $response->getBody());
        /** @var array<array-key, mixed> $data */
        $data = json_decode((string) $response->getBody(), true);

        return $data;
    }
}
