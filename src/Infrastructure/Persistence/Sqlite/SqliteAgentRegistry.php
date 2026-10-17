<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Agent\Agent;
use App\Domain\Agent\AgentId;
use App\Domain\Contract\AgentRegistry;
use Override;
use PDO;

final class SqliteAgentRegistry implements AgentRegistry
{
    public function __construct(
        private PDO $pdo,
        private AgentMapper $mapper,
    ) {
    }

    #[Override]
    public function all(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT * FROM agents ORDER BY name')->fetchAll();

        return array_map(fn(array $row): Agent => $this->mapper->fromRow($row), $rows);
    }

    #[Override]
    public function find(AgentId $id): ?Agent
    {
        $statement = $this->pdo->prepare('SELECT * FROM agents WHERE id = ?');
        $statement->execute([$id->value()]);
        /** @var array<array-key, mixed>|false $row */
        $row = $statement->fetch();

        return is_array($row) ? $this->mapper->fromRow($row) : null;
    }

    #[Override]
    public function save(Agent $agent): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO agents (id, name, description, active, profile, model, fallback_model, interval, workspace)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(id) DO UPDATE SET name = excluded.name, description = excluded.description,
                 active = excluded.active, profile = excluded.profile,
                 model = excluded.model, fallback_model = excluded.fallback_model,
                 interval = excluded.interval, workspace = excluded.workspace',
        );
        $statement->execute([
            $agent->id()->value(),
            $agent->name(),
            $agent->description(),
            $agent->isActive() ? 1 : 0,
            $agent->profile(),
            $agent->model(),
            $agent->fallbackModel(),
            $agent->interval(),
            $agent->workspace(),
        ]);
    }

    #[Override]
    public function delete(AgentId $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM agents WHERE id = ?');
        $statement->execute([$id->value()]);
    }
}
