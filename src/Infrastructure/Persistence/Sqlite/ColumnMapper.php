<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Agent\AgentId;
use App\Domain\Workflow\Column;
use App\Domain\Workflow\ColumnId;
use App\Domain\Workflow\Position;
use App\Domain\Workflow\Transitions;

final class ColumnMapper
{
    /** @param array<array-key, mixed> $row */
    public function fromRow(array $row, Transitions $transitions): Column
    {
        return new Column(
            new ColumnId((string) $row['id']),
            (string) $row['name'],
            new Position((int) $row['position']),
            (bool) $row['is_final'],
            $this->roleFrom($row['role_id'] ?? null),
            $transitions,
        );
    }

    private function roleFrom(mixed $value): ?AgentId
    {
        return is_string($value) && $value !== '' ? new AgentId($value) : null;
    }
}
