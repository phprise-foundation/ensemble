<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Contract\ColumnRepository;
use App\Domain\Workflow\Column;
use App\Domain\Workflow\ColumnCollection;
use App\Domain\Workflow\ColumnId;
use App\Domain\Workflow\Transitions;
use Override;
use PDO;

final class SqliteColumnRepository implements ColumnRepository
{
    public function __construct(
        private PDO $pdo,
        private ColumnMapper $mapper,
    ) {
    }

    #[Override]
    public function all(): ColumnCollection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT * FROM columns ORDER BY position')->fetchAll();
        $targets = $this->transitionsByColumn();

        return new ColumnCollection(array_map(
            fn(array $row): Column => $this->mapper->fromRow($row, $targets[(string) $row['id']] ?? new Transitions()),
            $rows,
        ));
    }

    #[Override]
    public function find(ColumnId $id): ?Column
    {
        $statement = $this->pdo->prepare('SELECT * FROM columns WHERE id = ?');
        $statement->execute([$id->value()]);
        /** @var array<array-key, mixed>|false $row */
        $row = $statement->fetch();

        return is_array($row) ? $this->mapper->fromRow($row, $this->transitionsFor($id)) : null;
    }

    #[Override]
    public function save(Column $column): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO columns (id, name, position, is_final, role_id) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT(id) DO UPDATE SET name = excluded.name, position = excluded.position,
                 is_final = excluded.is_final, role_id = excluded.role_id',
        );
        $statement->execute([
            $column->id()->value(),
            $column->name(),
            $column->position()->value(),
            $column->isFinal() ? 1 : 0,
            $column->role()?->value(),
        ]);
        $this->replaceTransitions($column);
    }

    #[Override]
    public function delete(ColumnId $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM columns WHERE id = ?');
        $statement->execute([$id->value()]);
    }

    /** @return array<string, Transitions> */
    private function transitionsByColumn(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query(
            'SELECT from_column_id, to_column_id FROM column_transitions ORDER BY from_column_id, position',
        )->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['from_column_id']][] = new ColumnId((string) $row['to_column_id']);
        }

        return array_map(static fn(array $targets): Transitions => new Transitions($targets), $grouped);
    }

    private function transitionsFor(ColumnId $id): Transitions
    {
        $statement = $this->pdo->prepare(
            'SELECT to_column_id FROM column_transitions WHERE from_column_id = ? ORDER BY position',
        );
        $statement->execute([$id->value()]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return new Transitions(array_map(
            static fn(array $row): ColumnId => new ColumnId((string) $row['to_column_id']),
            $rows,
        ));
    }

    private function replaceTransitions(Column $column): void
    {
        $delete = $this->pdo->prepare('DELETE FROM column_transitions WHERE from_column_id = ?');
        $delete->execute([$column->id()->value()]);
        $insert = $this->pdo->prepare(
            'INSERT INTO column_transitions (from_column_id, to_column_id, position) VALUES (?, ?, ?)',
        );
        foreach ($column->transitions()->all() as $position => $target) {
            $insert->execute([$column->id()->value(), $target->value(), $position]);
        }
    }
}
