<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Application\ReadConversation;
use App\Application\SendChatMessage;
use App\Infrastructure\Authentication\PasswordAuthenticator;
use App\Infrastructure\Chat\EchoChatAgent;
use App\Infrastructure\Persistence\JsonChatRepository;
use App\Infrastructure\Persistence\JsonFile;
use App\Infrastructure\Persistence\MessageMapper;
use App\Interface\Http\ChatController;
use App\Interface\Http\ChatMessagePresenter;
use App\Interface\Http\JsonResponder;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ChatControllerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'chat');
        unlink($this->file);
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testPostStoresConversationAndAnswers(): void
    {
        $response = $this->controller()->handle($this->post('hi'));

        self::assertSame(201, $response->getStatusCode());
        $messages = $this->decode($response)['messages'];
        self::assertCount(2, $messages);
        self::assertSame('user', $messages[0]['role']);
        self::assertSame('hi', $messages[0]['body']);
        self::assertSame('assistant', $messages[1]['role']);
    }

    public function testGetReturnsStoredConversation(): void
    {
        $controller = $this->controller();
        $controller->handle($this->post('hi'));

        $messages = $this->decode($controller->handle($this->get()))['messages'];

        self::assertCount(2, $messages);
    }

    public function testRejectsMissingToken(): void
    {
        $response = $this->controller()->handle(new ServerRequest('GET', '/api/chat'));

        self::assertSame(401, $response->getStatusCode());
    }

    private function controller(): ChatController
    {
        $repository = new JsonChatRepository(new JsonFile($this->file), new MessageMapper());

        return new ChatController(
            new PasswordAuthenticator('secret'),
            new ReadConversation($repository),
            new SendChatMessage($repository, new EchoChatAgent()),
            new ChatMessagePresenter(),
            new JsonResponder(),
        );
    }

    private function post(string $message): ServerRequest
    {
        return new ServerRequest(
            'POST',
            '/api/chat',
            ['Authorization' => 'Bearer secret', 'Content-Type' => 'application/json'],
            (string) json_encode(['message' => $message]),
        );
    }

    private function get(): ServerRequest
    {
        return new ServerRequest('GET', '/api/chat', ['Authorization' => 'Bearer secret']);
    }

    /** @return array<array-key, mixed> */
    private function decode(ResponseInterface $response): array
    {
        /** @var array<array-key, mixed> $data */
        $data = json_decode((string) $response->getBody(), true);

        return $data;
    }
}
