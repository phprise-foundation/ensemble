<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Contract\ItemRepository;
use App\Domain\Workflow\ColumnId;
use Override;
use PDO;

final class SqliteItemRepository implements ItemRepository
{
    public function __construct(
        private PDO $pdo,
        private ItemMapper $mapper,
    ) {
    }

    #[Override]
    public function all(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT * FROM items ORDER BY id')->fetchAll();
        $comments = $this->commentsByItem();

        return array_map(fn(array $row): Item => $this->mapper->fromRow($row, $comments[(int) $row['id']] ?? []), $rows);
    }

    #[Override]
    public function find(ItemId $id): ?Item
    {
        $statement = $this->pdo->prepare('SELECT * FROM items WHERE id = ?');
        $statement->execute([$id->toInt()]);
        /** @var array<array-key, mixed>|false $row */
        $row = $statement->fetch();

        return is_array($row) ? $this->mapper->fromRow($row, $this->commentsFor($id)) : null;
    }

    #[Override]
    public function nextId(): ItemId
    {
        $max = (int) $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM items')->fetchColumn();

        return new ItemId($max + 1);
    }

    #[Override]
    public function countInColumn(ColumnId $column): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM items WHERE column_id = ?');
        $statement->execute([$column->value()]);

        return (int) $statement->fetchColumn();
    }

    #[Override]
    public function add(Item $item): void
    {
        $this->pdo->prepare(
            'INSERT INTO items (id, column_id, agent_id, title, body, worker, blocked, blocked_by, closed, created_at, updated_at, entered_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute($this->values($item));
        $this->saveComments($item);
    }

    #[Override]
    public function save(Item $item): void
    {
        $this->pdo->prepare(
            'UPDATE items SET column_id = ?, agent_id = ?, title = ?, body = ?, worker = ?, blocked = ?, blocked_by = ?, closed = ?, updated_at = ?, entered_at = ? WHERE id = ?',
        )->execute([
            $item->column()->value(),
            $item->agent()?->value(),
            $item->title(),
            $item->body(),
            $item->worker(),
            $item->blocked() ? 1 : 0,
            $item->blockedBy(),
            $item->closed() ? 1 : 0,
            $item->updatedAt()->format(DATE_ATOM),
            $item->enteredAt()->format(DATE_ATOM),
            $item->id()->toInt(),
        ]);
        $this->pdo->prepare('DELETE FROM comments WHERE item_id = ?')->execute([$item->id()->toInt()]);
        $this->saveComments($item);
    }

    /** @return list<mixed> */
    private function values(Item $item): array
    {
        return [
            $item->id()->toInt(),
            $item->column()->value(),
            $item->agent()?->value(),
            $item->title(),
            $item->body(),
            $item->worker(),
            $item->blocked() ? 1 : 0,
            $item->blockedBy(),
            $item->closed() ? 1 : 0,
            $item->createdAt()->format(DATE_ATOM),
            $item->updatedAt()->format(DATE_ATOM),
            $item->enteredAt()->format(DATE_ATOM),
        ];
    }

    private function saveComments(Item $item): void
    {
        $statement = $this->pdo->prepare('INSERT INTO comments (item_id, author, body, created_at) VALUES (?, ?, ?, ?)');
        foreach ($item->comments()->all() as $comment) {
            $statement->execute([
                $item->id()->toInt(),
                $comment->author(),
                $comment->body(),
                $comment->createdAt()->format(DATE_ATOM),
            ]);
        }
    }

    /** @return array<int, list<array<string, mixed>>> */
    private function commentsByItem(): array
    {
        /** @var array<int, list<array<string, mixed>>> $grouped */
        $grouped = [];
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT * FROM comments ORDER BY id')->fetchAll();
        foreach ($rows as $row) {
            $grouped[(int) $row['item_id']][] = $row;
        }

        return $grouped;
    }

    /** @return list<array<string, mixed>> */
    private function commentsFor(ItemId $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM comments WHERE item_id = ? ORDER BY id');
        $statement->execute([$id->toInt()]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }
}
