<?php

declare(strict_types=1);

namespace App\Domain\Board;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, Comment> */
final class CommentCollection implements Countable, IteratorAggregate
{
    /** @var Comment[] */
    private array $comments;

    /** @param Comment[] $comments */
    public function __construct(array $comments)
    {
        $this->comments = array_values($comments);
    }

    /** @return Comment[] */
    public function all(): array
    {
        return $this->comments;
    }

    #[Override]
    public function count(): int
    {
        return count($this->comments);
    }

    public function last(): ?Comment
    {
        return $this->comments !== [] ? $this->comments[array_key_last($this->comments)] : null;
    }

    public function append(Comment $comment): self
    {
        $this->comments[] = $comment;

        return $this;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        yield from $this->comments;
    }
}
