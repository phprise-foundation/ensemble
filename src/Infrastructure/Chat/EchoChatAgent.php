<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Contract\ChatAgent;
use DateTimeImmutable;
use Override;

final readonly class EchoChatAgent implements ChatAgent
{
    #[Override]
    public function reply(Conversation $conversation): Message
    {
        return new Message(MessageRole::Assistant, $this->answer($conversation), new DateTimeImmutable());
    }

    private function answer(Conversation $conversation): string
    {
        $prompt = $conversation->last()?->body() ?? '';

        return sprintf('Copilot (offline stub): I received "%s". Wire the chat service for real answers.', $prompt);
    }
}
