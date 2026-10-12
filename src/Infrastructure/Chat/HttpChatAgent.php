<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Contract\ChatAgent;
use App\Domain\Exception\ChatUnavailableException;
use DateTimeImmutable;
use Override;

final readonly class HttpChatAgent implements ChatAgent
{
    public function __construct(
        private HttpTransport $transport,
        private string $url,
        private string $token,
        private int $timeout = 120,
    ) {
    }

    #[Override]
    public function reply(Conversation $conversation): Message
    {
        $response = $this->transport->post(
            rtrim($this->url, '/') . '/reply',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ],
            $this->payload($conversation),
            $this->timeout,
        );

        return new Message(MessageRole::Assistant, $this->replyFrom($response), new DateTimeImmutable());
    }

    private function payload(Conversation $conversation): string
    {
        $messages = array_map(
            static fn(Message $message): array => [
                'role' => $message->role()->value,
                'body' => $message->body(),
            ],
            $conversation->all(),
        );

        return (string) json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array{status: int, body: string} $response */
    private function replyFrom(array $response): string
    {
        /** @var mixed $decoded */
        $decoded = json_decode($response['body'], true);
        $reply = is_array($decoded) ? ($decoded['reply'] ?? null) : null;
        if ($response['status'] >= 400 || !is_string($reply)) {
            throw ChatUnavailableException::fromStatus($response['status']);
        }

        return $reply;
    }
}
