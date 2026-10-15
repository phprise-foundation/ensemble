<?php

declare(strict_types=1);

namespace App\Domain\Workflow;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, Column> */
final class ColumnCollection implements Countable, IteratorAggregate
{
    /** @var Column[] */
    private array $columns;

    /** @param Column[] $columns */
    public function __construct(array $columns)
    {
        $this->columns = $columns;
        usort($this->columns, static fn(Column $a, Column $b): int => $a->position()->value() <=> $b->position()->value());
    }

    /** @return Column[] */
    public function all(): array
    {
        return $this->columns;
    }

    #[Override]
    public function count(): int
    {
        return count($this->columns);
    }

    public function has(ColumnId $id): bool
    {
        return $this->find($id) !== null;
    }

    public function find(ColumnId $id): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->id()->equals($id)) {
                return $column;
            }
        }

        return null;
    }

    public function first(): ?Column
    {
        return $this->columns[0] ?? null;
    }

    public function final(): ?Column
    {
        foreach (array_reverse($this->columns) as $column) {
            if ($column->isFinal()) {
                return $column;
            }
        }

        return null;
    }

    public function nextPosition(): Position
    {
        $highest = array_reduce(
            $this->columns,
            static fn(int $carry, Column $column): int => max($carry, $column->position()->value()),
            -1,
        );

        return new Position($highest + 1);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        yield from $this->columns;
    }
}
