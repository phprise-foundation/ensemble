<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Board\Comment;
use App\Domain\Board\CommentCollection;
use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Board\Role;
use App\Domain\Board\Status;
use DateTimeImmutable;

final class ItemMapper
{
    public function toArray(Item $item): array
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
            'comments' => $this->commentsToArray($item->comments()),
        ];
    }

    public function fromArray(array $data): Item
    {
        $item = new Item(
            new ItemId((int) $data['id']),
            (string) ($data['title'] ?? ''),
            (string) ($data['body'] ?? ''),
            Status::from((string) $data['status']),
            $this->roleFrom($data['role'] ?? null),
            new DateTimeImmutable((string) $data['created_at']),
        );
        $this->hydrateState($item, $data);

        return $item;
    }

    private function roleFrom(mixed $role): ?Role
    {
        return $role === null || $role === '' ? null : Role::from((string) $role);
    }

    private function hydrateState(Item $item, array $data): void
    {
        $this->hydrateWorker($item, $data);
        $this->hydrateBlocked($item, $data);
        $this->hydrateClosing($item, $data);
        $this->hydrateComments($item, $data);
    }

    private function hydrateWorker(Item $item, array $data): void
    {
        if (!empty($data['worker'])) {
            $item->assignWorker((string) $data['worker']);
        }
    }

    private function hydrateBlocked(Item $item, array $data): void
    {
        if (!empty($data['blocked'])) {
            $item->block((string) ($data['blocked_by'] ?? ''));
        }
    }

    private function hydrateClosing(Item $item, array $data): void
    {
        if (!empty($data['closed'])) {
            $item->close();
        }
    }

    private function hydrateComments(Item $item, array $data): void
    {
        foreach ($this->commentsFromArray((array) ($data['comments'] ?? [])) as $comment) {
            $item->addComment($comment);
        }
    }

    /** @return list<array{author: string, body: string, created_at: string}> */
    private function commentsToArray(CommentCollection $comments): array
    {
        return array_values(array_map(static fn(Comment $comment): array => [
            'author' => $comment->author(),
            'body' => $comment->body(),
            'created_at' => $comment->createdAt()->format(DATE_ATOM),
        ], $comments->all()));
    }

    /**
     * @param array<array-key, mixed> $comments
     * @return list<Comment>
     */
    private function commentsFromArray(array $comments): array
    {
        return array_values(array_map(static fn(array $comment): Comment => new Comment(
            (string) ($comment['author'] ?? ''),
            (string) ($comment['body'] ?? ''),
            new DateTimeImmutable((string) ($comment['created_at'] ?? 'now')),
        ), $comments));
    }
}
