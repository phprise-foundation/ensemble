<?php

declare(strict_types=1);

namespace App\Domain\Workflow;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, ColumnId> */
final class Transitions implements Countable, IteratorAggregate
{
    /** @var ColumnId[] */
    private array $targets;

    /** @param ColumnId[] $targets */
    public function __construct(array $targets = [])
    {
        $this->targets = $targets;
    }

    /** @return ColumnId[] */
    public function all(): array
    {
        return $this->targets;
    }

    #[Override]
    public function count(): int
    {
        return count($this->targets);
    }

    public function allows(ColumnId $target): bool
    {
        foreach ($this->targets as $candidate) {
            if ($candidate->equals($target)) {
                return true;
            }
        }

        return false;
    }

    public function sole(): ?ColumnId
    {
        return count($this->targets) === 1 ? $this->targets[0] : null;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        yield from $this->targets;
    }
}
