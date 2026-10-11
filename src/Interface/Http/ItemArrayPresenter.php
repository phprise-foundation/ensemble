<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Board\Comment;
use App\Domain\Board\CommentCollection;
use App\Domain\Board\Item;

final class ItemArrayPresenter
{
    public function present(Item $item): array
    {
        return [
            'id' => $item->id()->toInt(),
            'title' => $item->title(),
            'body' => $item->body(),
            'status' => $item->status()->value,
            'role' => $item->role()?->value,
            'worker' => $item->worker(),
            'blocked' => $item->blocked(),
            'blocked_by' => $item->blockedBy(),
            'created_at' => $item->createdAt()->format(DATE_ATOM),
            'closed' => $item->closed(),
            'comments' => $this->presentComments($item->comments()),
        ];
    }

    /** @param Item[] $items */
    public function presentMany(array $items): array
    {
        return array_map(fn(Item $item): array => $this->present($item), $items);
    }

    /** @return array{author: string, body: string, created_at: string} */
    public function presentComment(Comment $comment): array
    {
        return [
            'author' => $comment->author(),
            'body' => $comment->body(),
            'created_at' => $comment->createdAt()->format(DATE_ATOM),
        ];
    }

    /** @return list<array{author: string, body: string, created_at: string}> */
    private function presentComments(CommentCollection $comments): array
    {
        return array_values(array_map(fn(Comment $comment): array => $this->presentComment($comment), $comments->all()));
    }
}
