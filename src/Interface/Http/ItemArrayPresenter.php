<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Board\Comment;
use App\Domain\Board\CommentCollection;
use App\Domain\Board\Item;
use App\Domain\Board\ItemEvent;
use App\Domain\Board\ItemId;

final class ItemArrayPresenter
{
    public function present(Item $item): array
    {
        return [
            'id' => $item->id()->toInt(),
            'title' => $item->title(),
            'body' => $item->body(),
            'type' => $item->type()->value,
            'parent_id' => $item->parent()?->toInt(),
            'status' => $item->column()->value(),
            'role' => $item->agent()?->value(),
            'worker' => $item->worker(),
            'blocked' => $item->blocked(),
            'blocked_by' => $item->blockedBy(),
            'created_at' => $item->createdAt()->format(DATE_ATOM),
            'updated_at' => $item->updatedAt()->format(DATE_ATOM),
            'entered_at' => $item->enteredAt()->format(DATE_ATOM),
            'closed' => $item->closed(),
            'comments' => $this->presentComments($item->comments()),
        ];
    }

    /**
     * @param Item[] $items
     * @return list<array<array-key, mixed>>
     */
    public function presentMany(array $items): array
    {
        return array_values(array_map(fn(Item $item): array => $this->present($item), $items));
    }

    /**
     * @param ItemEvent[] $history
     * @return array<array-key, mixed>
     */
    public function presentWithHistory(Item $item, array $history): array
    {
        return $this->present($item) + ['history' => array_map(
            static fn(ItemEvent $event): array => [
                'from' => $event->from()?->value(),
                'to' => $event->to()->value(),
                'actor' => $event->actor(),
                'at' => $event->at()->format(DATE_ATOM),
            ],
            $history,
        )];
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

    /**
     * @param ItemId[] $ids
     * @return list<int>
     */
    public function presentIds(array $ids): array
    {
        return array_values(array_map(fn(ItemId $id): int => $id->toInt(), $ids));
    }
}
