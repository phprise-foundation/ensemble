<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Application\AdjudicateInfraction;
use App\Application\CreateItem;
use App\Application\DeclareInfraction;
use App\Application\ListItemInfractions;
use App\Infrastructure\Authentication\PasswordAuthenticator;
use App\Infrastructure\Persistence\Sqlite\ColumnMapper;
use App\Infrastructure\Persistence\Sqlite\ItemMapper;
use App\Infrastructure\Persistence\Sqlite\SqliteColumnRepository;
use App\Infrastructure\Persistence\Sqlite\SqliteDatabase;
use App\Infrastructure\Persistence\Sqlite\SqliteInfractionRepository;
use App\Infrastructure\Persistence\Sqlite\SqliteItemEventLog;
use App\Infrastructure\Persistence\Sqlite\SqliteItemRepository;
use App\Interface\Http\InfractionController;
use App\Interface\Http\InfractionPresenter;
use App\Interface\Http\JsonResponder;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class InfractionApiTest extends TestCase
{
    private string $file;
    private InfractionController $controller;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'infractions');
        unlink($this->file);
        $pdo = (new SqliteDatabase($this->file))->connection();
        $items = new SqliteItemRepository($pdo, new ItemMapper());
        $columns = new SqliteColumnRepository($pdo, new ColumnMapper());
        $events = new SqliteItemEventLog($pdo);
        $infractions = new SqliteInfractionRepository($pdo);

        (new CreateItem($items, $columns, $events))->execute('Do it', '', null, null, 'user');

        $this->controller = new InfractionController(
            new PasswordAuthenticator('secret'),
            new DeclareInfraction($items, $infractions),
            new AdjudicateInfraction($infractions),
            new ListItemInfractions($infractions),
            new InfractionPresenter(),
            new JsonResponder(),
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testDeclaresListsAndAdjudicates(): void
    {
        $declared = $this->json($this->controller->handle($this->request('POST', '/api/items/1/infractions', [
            'infractor' => ['agent' => 'Kaio', 'role' => 'coder', 'model' => 'm/1'],
            'detected_by' => 'Alexya',
            'cause' => 'bug',
            'severity' => 'major',
            'evidence' => 'the suite is red',
            'blocked' => true,
        ])), 201);

        self::assertSame('declared', $declared['infraction']['status']);
        self::assertSame('Kaio', $declared['infraction']['infractor']['agent']);
        self::assertTrue($declared['infraction']['blocked']);

        $list = $this->json($this->controller->handle($this->request('GET', '/api/items/1/infractions')));
        self::assertCount(1, $list['infractions']);

        $upheld = $this->json($this->controller->handle($this->request('POST', '/api/infractions/1/adjudicate', [
            'status' => 'upheld',
            'adjudicated_by' => 'Alan',
        ])));

        self::assertSame('upheld', $upheld['infraction']['status']);
        self::assertSame('Alan', $upheld['infraction']['adjudicated_by']);
        self::assertNotNull($upheld['infraction']['resolved_at']);
    }

    public function testRejectsSelfInfraction(): void
    {
        $response = $this->controller->handle($this->request('POST', '/api/items/1/infractions', [
            'infractor' => ['agent' => 'Kaio'],
            'detected_by' => 'Kaio',
            'cause' => 'bug',
            'severity' => 'major',
            'evidence' => 'x',
        ]));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsMissingEvidence(): void
    {
        $response = $this->controller->handle($this->request('POST', '/api/items/1/infractions', [
            'infractor' => ['agent' => 'Kaio'],
            'detected_by' => 'Alexya',
            'cause' => 'bug',
            'severity' => 'major',
        ]));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsDetectorAsAdjudicator(): void
    {
        $this->controller->handle($this->request('POST', '/api/items/1/infractions', [
            'infractor' => ['agent' => 'Kaio'],
            'detected_by' => 'Alexya',
            'cause' => 'bug',
            'severity' => 'major',
            'evidence' => 'x',
        ]));

        $response = $this->controller->handle($this->request('POST', '/api/infractions/1/adjudicate', [
            'status' => 'upheld',
            'adjudicated_by' => 'Alexya',
        ]));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsUnknownItem(): void
    {
        $response = $this->controller->handle($this->request('POST', '/api/items/999/infractions', [
            'infractor' => ['agent' => 'Kaio'],
            'detected_by' => 'Alexya',
            'cause' => 'bug',
            'severity' => 'major',
            'evidence' => 'x',
        ]));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRejectsMissingToken(): void
    {
        $response = $this->controller->handle(new ServerRequest('GET', '/api/items/1/infractions'));

        self::assertSame(401, $response->getStatusCode());
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
