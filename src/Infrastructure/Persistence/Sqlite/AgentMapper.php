<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Agent\Agent;
use App\Domain\Agent\AgentId;

final class AgentMapper
{
    /** @param array<array-key, mixed> $row */
    public function fromRow(array $row): Agent
    {
        return new Agent(
            new AgentId((string) $row['id']),
            (string) $row['name'],
            (string) $row['description'],
            (bool) $row['active'],
            (string) ($row['rule'] ?? ''),
            (string) ($row['profile'] ?? ''),
            (string) ($row['model'] ?? ''),
            (string) ($row['fallback_model'] ?? ''),
            (int) ($row['interval'] ?? 300),
            (string) ($row['workspace'] ?? '/workspace'),
        );
    }
}
