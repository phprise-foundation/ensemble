<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Contract\TriggerRepository;
use App\Domain\Trigger\Trigger;
use Override;
use PDO;

final class SqliteTriggerRepository implements TriggerRepository
{
    public function __construct(
        private PDO $pdo,
        private TriggerMapper $mapper,
    ) {
    }

    #[Override]
    public function forEvent(string $eventType): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM triggers WHERE event_type = ? AND enabled = 1 ORDER BY priority',
        );
        $statement->execute([$eventType]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(fn(array $row): Trigger => $this->mapper->fromRow($row), $rows);
    }
}
