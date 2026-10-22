<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\Transition;

final class TransitionPresenter
{
    /** @return array<string, mixed> */
    public function present(Transition $transition): array
    {
        return [
            'id' => $transition->id()->value(),
            'workflowId' => $transition->workflowId()->value(),
            'fromStatusId' => $transition->fromStatusId()->value(),
            'toStatusId' => $transition->toStatusId()->value(),
            'actor' => $transition->actor()->value,
            'outcome' => $transition->outcome()?->value(),
            'createdAt' => $transition->createdAt()->format(DATE_ATOM),
            'updatedAt' => $transition->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Transition> $transitions
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $transitions): array
    {
        return array_map(fn(Transition $transition): array => $this->present($transition), $transitions);
    }
}
