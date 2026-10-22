<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

final readonly class StatusIds
{
    /** @var list<StatusId> */
    private array $ids;

    /** @param list<StatusId> $ids */
    public function __construct(array $ids = [])
    {
        $seen = [];
        $unique = [];
        foreach ($ids as $id) {
            if (in_array($id->value(), $seen, true)) {
                continue;
            }
            $seen[] = $id->value();
            $unique[] = $id;
        }

        $this->ids = $unique;
    }

    /** @return list<StatusId> */
    public function all(): array
    {
        return $this->ids;
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }
}
