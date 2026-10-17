<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\AgentId;
use App\Domain\Board\Item;
use App\Domain\Contract\TriggerRepository;
use App\Domain\Exception\InvalidTriggerActionException;
use App\Domain\Trigger\Action;
use App\Domain\Trigger\Trigger;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;

final readonly class TriggerEngine
{
    public function __construct(private TriggerRepository $triggers)
    {
    }

    public function run(string $eventType, Item $item): void
    {
        $at = new DateTimeImmutable();
        foreach ($this->triggers->forEvent($eventType) as $trigger) {
            $this->fire($trigger, $item, $at);
        }
    }

    private function fire(Trigger $trigger, Item $item, DateTimeImmutable $at): void
    {
        if (!$this->matches($trigger, $item)) {
            return;
        }
        foreach ($trigger->actions() as $action) {
            $this->apply($action, $item, $at);
        }
    }

    private function matches(Trigger $trigger, Item $item): bool
    {
        $context = $this->context($item);
        foreach ($trigger->conditions() as $condition) {
            if (!$condition->matches($context[$condition->field()] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function context(Item $item): array
    {
        return [
            'status' => $item->column()->value(),
            'role' => $item->agent()?->value(),
            'worker' => $item->worker(),
            'blocked' => $item->blocked(),
            'closed' => $item->closed(),
            'issue_type' => $item->type()->value,
        ];
    }

    private function apply(Action $action, Item $item, DateTimeImmutable $at): void
    {
        match ($action->field()) {
            'status' => $item->moveTo(new ColumnId((string) $action->value()), $at),
            'role' => $this->applyRole($action, $item, $at),
            'worker' => $this->applyWorker($action, $item, $at),
            'closed' => $this->applyClosed($action, $item, $at),
            default => throw InvalidTriggerActionException::forField($action->field()),
        };
    }

    private function applyRole(Action $action, Item $item, DateTimeImmutable $at): void
    {
        $value = (string) $action->value();
        $item->assignAgent($value === '' ? null : new AgentId($value), $at);
    }

    private function applyWorker(Action $action, Item $item, DateTimeImmutable $at): void
    {
        $value = (string) $action->value();
        if ($value === '') {
            $item->clearWorker($at);

            return;
        }
        $item->assignWorker($value, $at);
    }

    private function applyClosed(Action $action, Item $item, DateTimeImmutable $at): void
    {
        if ($action->value() === true) {
            $item->close($at);
        }
    }
}
