<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Infrastructure\Persistence\JsonChatRepository;
use App\Infrastructure\Persistence\JsonFile;
use App\Infrastructure\Persistence\MessageMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class JsonChatRepositoryTest extends TestCase
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

    public function testAppendsAndReadsMessagesInOrder(): void
    {
        $repository = new JsonChatRepository(new JsonFile($this->file), new MessageMapper());
        $repository->append(new Message(MessageRole::User, 'hello', new DateTimeImmutable()));
        $repository->append(new Message(MessageRole::Assistant, 'hi', new DateTimeImmutable()));

        $conversation = $repository->conversation();

        self::assertCount(2, $conversation);
        self::assertSame('hello', $conversation->all()[0]->body());
        self::assertSame(MessageRole::Assistant, $conversation->last()?->role());
    }
}
