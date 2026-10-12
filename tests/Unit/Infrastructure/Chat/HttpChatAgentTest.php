<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Chat;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Exception\ChatUnavailableException;
use App\Infrastructure\Chat\HttpChatAgent;
use App\Infrastructure\Chat\HttpTransport;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HttpChatAgentTest extends TestCase
{
    public function testSendsConversationAndReturnsReply(): void
    {
        $transport = new RecordingTransport(['status' => 200, 'body' => '{"reply":"hi there"}']);

        $answer = (new HttpChatAgent($transport, 'http://chat/', 'secret'))->reply($this->conversation());

        self::assertSame('hi there', $answer->body());
        self::assertSame(MessageRole::Assistant, $answer->role());
        self::assertSame('http://chat/reply', $transport->url);
        self::assertSame('Bearer secret', $transport->headers['Authorization']);
        self::assertSame(
            ['messages' => [['role' => 'user', 'body' => 'hello']]],
            json_decode($transport->body, true),
        );
    }

    public function testThrowsWhenServiceReturnsError(): void
    {
        $transport = new RecordingTransport(['status' => 500, 'body' => '{"error":"boom"}']);

        $this->expectException(ChatUnavailableException::class);

        (new HttpChatAgent($transport, 'http://chat', 'secret'))->reply($this->conversation());
    }

    public function testThrowsWhenReplyIsMalformed(): void
    {
        $transport = new RecordingTransport(['status' => 200, 'body' => 'not json']);

        $this->expectException(ChatUnavailableException::class);

        (new HttpChatAgent($transport, 'http://chat', 'secret'))->reply($this->conversation());
    }

    private function conversation(): Conversation
    {
        return new Conversation([new Message(MessageRole::User, 'hello', new DateTimeImmutable())]);
    }
}

final class RecordingTransport implements HttpTransport
{
    public ?string $url = null;

    /** @var array<string, string> */
    public array $headers = [];

    public string $body = '';

    /** @param array{status: int, body: string} $response */
    public function __construct(private array $response)
    {
    }

    public function post(string $url, array $headers, string $body, int $timeout): array
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;

        return $this->response;
    }
}
