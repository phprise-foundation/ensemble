<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;

final class ChatMessagePresenter
{
    /** @return array{role: string, body: string, created_at: string} */
    public function present(Message $message): array
    {
        return [
            'role' => $message->role()->value,
            'body' => $message->body(),
            'created_at' => $message->createdAt()->format(DATE_ATOM),
        ];
    }

    /** @return list<array{role: string, body: string, created_at: string}> */
    public function presentMany(Conversation $conversation): array
    {
        return array_values(array_map(fn(Message $message): array => $this->present($message), $conversation->all()));
    }
}
