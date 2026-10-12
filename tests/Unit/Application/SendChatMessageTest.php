<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\SendChatMessage;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Contract\ChatRepository;
use App\Infrastructure\Chat\EchoChatAgent;
use PHPUnit\Framework\TestCase;

final class SendChatMessageTest extends TestCase
{
    public function testStoresUserMessageAndAgentReply(): void
    {
        $repository = new InMemoryChatRepository();

        (new SendChatMessage($repository, new EchoChatAgent()))->execute('hello');

        $conversation = $repository->conversation();
        self::assertCount(2, $conversation);
        self::assertSame(MessageRole::User, $conversation->all()[0]->role());
        self::assertSame('hello', $conversation->all()[0]->body());
        self::assertSame(MessageRole::Assistant, $conversation->last()?->role());
    }
}

final class InMemoryChatRepository implements ChatRepository
{
    private Conversation $conversation;

    public function __construct()
    {
        $this->conversation = new Conversation();
    }

    public function conversation(): Conversation
    {
        return $this->conversation;
    }

    public function append(Message $message): void
    {
        $this->conversation->append($message);
    }
}
