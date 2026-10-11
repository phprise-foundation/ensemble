<?php

declare(strict_types=1);

use App\Application\AddComment;
use App\Application\CloseItem;
use App\Application\CreateItem;
use App\Application\FindItem;
use App\Application\ListBoardItems;
use App\Application\UpdateItem;
use App\Infrastructure\Authentication\PasswordAuthenticator;
use App\Infrastructure\Persistence\ItemMapper;
use App\Infrastructure\Persistence\JsonBoardRepository;
use App\Infrastructure\Persistence\JsonFile;
use App\Interface\Http\AuthController;
use App\Interface\Http\BoardController;
use App\Interface\Http\ItemArrayPresenter;
use App\Interface\Http\JsonResponder;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

require __DIR__ . '/../vendor/autoload.php';

$password = getenv('BOARD_PASSWORD');
$password = $password === false ? 'admin' : $password;
$dataFile = getenv('BOARD_DATA_FILE');
$dataFile = $dataFile === false ? __DIR__ . '/../var/board.json' : $dataFile;

$authenticator = new PasswordAuthenticator($password);
$repository = new JsonBoardRepository(new JsonFile($dataFile), new ItemMapper());
$responder = new JsonResponder();
$presenter = new ItemArrayPresenter();

$boardController = new BoardController(
    $authenticator,
    new ListBoardItems($repository),
    new FindItem($repository),
    new CreateItem($repository),
    new UpdateItem($repository),
    new AddComment($repository),
    new CloseItem($repository),
    $presenter,
    $responder,
);
$authController = new AuthController($authenticator, $responder);

$request = ServerRequest::fromGlobals();

$response = match (true) {
    $request->getUri()->getPath() === '/api/health' => $responder->respond(['ok' => true]),
    $request->getUri()->getPath() === '/api/login' => $authController->login($request),
    str_starts_with($request->getUri()->getPath(), '/api/items') => $boardController->handle($request),
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
