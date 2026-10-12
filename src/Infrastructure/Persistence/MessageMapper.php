<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use DateTimeImmutable;

final class MessageMapper
{
    /** @return array{role: string, body: string, created_at: string} */
    public function toArray(Message $message): array
    {
        return [
            'role' => $message->role()->value,
            'body' => $message->body(),
            'created_at' => $message->createdAt()->format(DATE_ATOM),
        ];
    }

    /** @param array<array-key, mixed> $data */
    public function fromArray(array $data): Message
    {
        return new Message(
            MessageRole::from((string) $data['role']),
            (string) ($data['body'] ?? ''),
            new DateTimeImmutable((string) ($data['created_at'] ?? 'now')),
        );
    }
}
