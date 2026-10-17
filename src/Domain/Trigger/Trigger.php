<?php

declare(strict_types=1);

namespace App\Domain\Trigger;

final readonly class Trigger
{
    /**
     * @param list<Condition> $conditions
     * @param list<Action> $actions
     */
    public function __construct(
        private TriggerId $id,
        private string $name,
        private string $eventType,
        private bool $enabled,
        private int $priority,
        private array $conditions,
        private array $actions,
    ) {
    }

    public function id(): TriggerId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /** @return list<Condition> */
    public function conditions(): array
    {
        return $this->conditions;
    }

    /** @return list<Action> */
    public function actions(): array
    {
        return $this->actions;
    }
}
