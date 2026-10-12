<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, Message> */
final class Conversation implements Countable, IteratorAggregate
{
    /** @var Message[] */
    private array $messages;

    /** @param Message[] $messages */
    public function __construct(array $messages = [])
    {
        $this->messages = array_values($messages);
    }

    /** @return Message[] */
    public function all(): array
    {
        return $this->messages;
    }

    #[Override]
    public function count(): int
    {
        return count($this->messages);
    }

    public function last(): ?Message
    {
        return $this->messages !== [] ? $this->messages[array_key_last($this->messages)] : null;
    }

    public function append(Message $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        yield from $this->messages;
    }
}
