<?php

declare(strict_types=1);

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
use App\Application\ReadConversation;
use App\Application\SendChatMessage;
use App\Application\UpdateAgent;
use App\Application\UpdateBoard;
use App\Application\UpdateColumn;
use App\Application\UpdateItem;
use App\Infrastructure\Authentication\PasswordAuthenticator;
use App\Infrastructure\Chat\CurlHttpTransport;
use App\Infrastructure\Chat\EchoChatAgent;
use App\Infrastructure\Chat\HttpChatAgent;
use App\Infrastructure\Persistence\JsonChatRepository;
use App\Infrastructure\Persistence\JsonFile;
use App\Infrastructure\Persistence\MessageMapper;
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
use App\Interface\Http\AuthController;
use App\Interface\Http\BoardController;
use App\Interface\Http\BoardPresenter;
use App\Interface\Http\ChatController;
use App\Interface\Http\ChatMessagePresenter;
use App\Interface\Http\ItemArrayPresenter;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\WorkflowController;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

require __DIR__ . '/../vendor/autoload.php';

set_time_limit(120);

$password = getenv('BOARD_PASSWORD');
$password = $password === false ? 'admin' : $password;
$databaseFile = getenv('BOARD_DB_FILE');
$databaseFile = $databaseFile === false ? __DIR__ . '/../var/board.sqlite' : $databaseFile;

$authenticator = new PasswordAuthenticator($password);
$responder = new JsonResponder();
$pdo = (new SqliteDatabase($databaseFile))->connection();

$items = new SqliteItemRepository($pdo, new ItemMapper());
$columns = new SqliteColumnRepository($pdo, new ColumnMapper());
$agents = new SqliteAgentRegistry($pdo, new AgentMapper());
$events = new SqliteItemEventLog($pdo);
$settings = new SqliteBoardSettings($pdo);

$boardController = new BoardController(
    $authenticator,
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

$workflowController = new WorkflowController(
    $authenticator,
    new ReadBoard($settings),
    new UpdateBoard($settings),
    new ListColumns($columns),
    new CreateColumn($columns, $agents),
    new UpdateColumn($columns, $agents),
    new DeleteColumn($columns, $items),
    new BoardPresenter(),
    $responder,
);

$agentController = new AgentController(
    $authenticator,
    new ListAgents($agents),
    new CreateAgent($agents),
    new UpdateAgent($agents),
    new DeleteAgent($agents),
    new AgentArrayPresenter(),
    $responder,
);

$authController = new AuthController($authenticator, $responder);

$chatFile = getenv('BOARD_CHAT_FILE');
$chatFile = $chatFile === false ? __DIR__ . '/../var/chat.json' : $chatFile;
$chatRepository = new JsonChatRepository(new JsonFile($chatFile), new MessageMapper());

$chatUrl = getenv('CHAT_URL');
$chatToken = getenv('CHAT_TOKEN');
$chatTimeout = getenv('CHAT_TIMEOUT');
$chatAgent = is_string($chatUrl) && $chatUrl !== ''
    ? new HttpChatAgent(new CurlHttpTransport(), $chatUrl, $chatToken === false ? '' : $chatToken, $chatTimeout === false ? 120 : (int) $chatTimeout)
    : new EchoChatAgent();

$chatController = new ChatController(
    $authenticator,
    new ReadConversation($chatRepository),
    new SendChatMessage($chatRepository, $chatAgent),
    new ChatMessagePresenter(),
    $responder,
);

$request = ServerRequest::fromGlobals();
$path = $request->getUri()->getPath();

$response = match (true) {
    $path === '/api/health' => $responder->respond(['ok' => true]),
    $path === '/api/login' => $authController->login($request),
    $path === '/api/chat' => $chatController->handle($request),
    $path === '/api/board' || str_starts_with($path, '/api/board/') => $workflowController->handle($request),
    str_starts_with($path, '/api/agents') => $agentController->handle($request),
    str_starts_with($path, '/api/items') => $boardController->handle($request),
    default => new Response(404, [], 'not found'),
};

http_response_code($response->getStatusCode());
/** @var array<string, list<string>> $headers */
$headers = $response->getHeaders();
foreach ($headers as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}
echo (string) $response->getBody();
