<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Board\ItemEvent;
use App\Domain\Board\ItemId;
use App\Domain\Contract\ItemEventLog;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;
use Override;
use PDO;

final class SqliteItemEventLog implements ItemEventLog
{
    public function __construct(private PDO $pdo)
    {
    }

    #[Override]
    public function record(ItemId $id, ItemEvent $event): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO item_events (item_id, from_column_id, to_column_id, actor, created_at) VALUES (?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $id->toInt(),
            $event->from()?->value(),
            $event->to()->value(),
            $event->actor(),
            $event->at()->format(DATE_ATOM),
        ]);
    }

    #[Override]
    public function history(ItemId $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM item_events WHERE item_id = ? ORDER BY id');
        $statement->execute([$id->toInt()]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(fn(array $row): ItemEvent => $this->fromRow($row), $rows);
    }

    #[Override]
    public function latest(ItemId $id): ?ItemEvent
    {
        $statement = $this->pdo->prepare('SELECT * FROM item_events WHERE item_id = ? ORDER BY id DESC LIMIT 1');
        $statement->execute([$id->toInt()]);
        /** @var array<array-key, mixed>|false $row */
        $row = $statement->fetch();

        return is_array($row) ? $this->fromRow($row) : null;
    }

    /** @param array<array-key, mixed> $row */
    private function fromRow(array $row): ItemEvent
    {
        return new ItemEvent(
            $this->columnFrom($row['from_column_id'] ?? null),
            new ColumnId((string) $row['to_column_id']),
            (string) $row['actor'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }

    private function columnFrom(mixed $value): ?ColumnId
    {
        return is_string($value) && $value !== '' ? new ColumnId($value) : null;
    }
}
