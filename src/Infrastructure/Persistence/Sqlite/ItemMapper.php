<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Agent\AgentId;
use App\Domain\Board\Comment;
use App\Domain\Board\IssueType;
use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;

final class ItemMapper
{
    /**
     * @param array<array-key, mixed> $row
     * @param list<array<array-key, mixed>> $comments
     */
    public function fromRow(array $row, array $comments): Item
    {
        $createdAt = new DateTimeImmutable((string) $row['created_at']);
        $item = new Item(
            new ItemId((int) $row['id']),
            new ColumnId((string) $row['column_id']),
            (string) $row['title'],
            (string) $row['body'],
            $this->agentFrom($row['agent_id'] ?? null),
            $createdAt,
            IssueType::from((string) ($row['issue_type'] ?? 'task')),
            $this->itemIdFrom($row['parent_id'] ?? null),
        );
        $this->hydrateWorker($item, $row, $createdAt);
        $this->hydrateBlocked($item, $row, $createdAt);
        $this->hydrateClosing($item, $row, $createdAt);
        $this->hydrateComments($item, $comments);
        $item->restoreTimestamps(
            new DateTimeImmutable((string) $row['updated_at']),
            new DateTimeImmutable((string) $row['entered_at']),
        );

        return $item;
    }

    private function agentFrom(mixed $id): ?AgentId
    {
        return $id === null || $id === '' ? null : new AgentId((string) $id);
    }

    private function itemIdFrom(mixed $id): ?ItemId
    {
        return $id === null || $id === '' ? null : new ItemId((int) $id);
    }

    /** @param array<array-key, mixed> $row */
    private function hydrateWorker(Item $item, array $row, DateTimeImmutable $at): void
    {
        if (($row['worker'] ?? '') !== '') {
            $item->assignWorker((string) $row['worker'], $at);
        }
    }

    /** @param array<array-key, mixed> $row */
    private function hydrateBlocked(Item $item, array $row, DateTimeImmutable $at): void
    {
        if (!empty($row['blocked'])) {
            $item->block((string) ($row['blocked_by'] ?? ''), $at);
        }
    }

    /** @param array<array-key, mixed> $row */
    private function hydrateClosing(Item $item, array $row, DateTimeImmutable $at): void
    {
        if (!empty($row['closed'])) {
            $item->close($at);
        }
    }

    /** @param list<array<array-key, mixed>> $comments */
    private function hydrateComments(Item $item, array $comments): void
    {
        foreach ($comments as $comment) {
            $item->addComment($this->commentFrom($comment), new DateTimeImmutable((string) $comment['created_at']));
        }
    }

    /** @param array<array-key, mixed> $row */
    private function commentFrom(array $row): Comment
    {
        return new Comment(
            (string) $row['author'],
            (string) $row['body'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
