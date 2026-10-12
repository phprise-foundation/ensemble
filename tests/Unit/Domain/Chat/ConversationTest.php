<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chat;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    public function testAppendsAndReturnsLast(): void
    {
        $conversation = new Conversation();
        $conversation->append($this->message('first'));
        $conversation->append($this->message('second'));

        self::assertCount(2, $conversation);
        self::assertSame('second', $conversation->last()?->body());
    }

    public function testEmptyConversationHasNoLast(): void
    {
        self::assertNull((new Conversation())->last());
    }

    private function message(string $body): Message
    {
        return new Message(MessageRole::User, $body, new DateTimeImmutable());
    }
}
