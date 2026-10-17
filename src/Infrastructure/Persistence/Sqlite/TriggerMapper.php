<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Trigger\Action;
use App\Domain\Trigger\Condition;
use App\Domain\Trigger\Operator;
use App\Domain\Trigger\Trigger;
use App\Domain\Trigger\TriggerId;

final class TriggerMapper
{
    /** @param array<array-key, mixed> $row */
    public function fromRow(array $row): Trigger
    {
        return new Trigger(
            new TriggerId((string) $row['id']),
            (string) $row['name'],
            (string) $row['event_type'],
            (bool) $row['enabled'],
            (int) $row['priority'],
            $this->conditions((string) $row['when_json']),
            $this->actions((string) $row['then_json']),
        );
    }

    /** @return list<Condition> */
    private function conditions(string $json): array
    {
        /** @var list<array<array-key, mixed>> $items */
        $items = json_decode($json, true) ?: [];

        return array_map(fn(array $item): Condition => new Condition(
            (string) $item['field'],
            Operator::from((string) $item['op']),
            $this->value($item['value'] ?? null),
        ), $items);
    }

    /** @return list<Action> */
    private function actions(string $json): array
    {
        /** @var list<array<array-key, mixed>> $items */
        $items = json_decode($json, true) ?: [];

        return array_map(fn(array $item): Action => new Action(
            (string) $item['field'],
            $this->value($item['value'] ?? null),
        ), $items);
    }

    private function value(mixed $value): int|string|bool
    {
        return is_bool($value) || is_int($value) ? $value : (string) $value;
    }
}
