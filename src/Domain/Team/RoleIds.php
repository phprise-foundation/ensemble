<?php

declare(strict_types=1);

namespace App\Domain\Team;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, RoleId> */
final class RoleIds implements Countable, IteratorAggregate
{
    /** @var list<RoleId> */
    private array $ids;

    /** @param list<RoleId> $ids */
    public function __construct(array $ids = [])
    {
        $this->ids = $this->unique($ids);
    }

    /** @param list<string> $values */
    public static function fromStrings(array $values): self
    {
        $ids = [];
        foreach ($values as $value) {
            $ids[] = new RoleId($value);
        }

        return new self($ids);
    }

    /** @return list<RoleId> */
    public function all(): array
    {
        return $this->ids;
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(fn(RoleId $id): string => $id->value(), $this->ids);
    }

    #[Override]
    public function count(): int
    {
        return count($this->ids);
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function contains(RoleId $id): bool
    {
        foreach ($this->ids as $candidate) {
            if ($candidate->equals($id)) {
                return true;
            }
        }

        return false;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        yield from $this->ids;
    }

    /**
     * @param list<RoleId> $ids
     * @return list<RoleId>
     */
    private function unique(array $ids): array
    {
        $unique = [];
        $seen = [];
        foreach ($ids as $id) {
            if (isset($seen[$id->value()])) {
                continue;
            }
            $seen[$id->value()] = true;
            $unique[] = $id;
        }

        return $unique;
    }
}
