<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\IssueEvent;

final class IssueEventPresenter
{
    /** @return array<string, mixed> */
    public function present(IssueEvent $event): array
    {
        return [
            'id' => $event->id()->value(),
            'type' => $event->type()->value,
            'fromStatusId' => $event->fromStatusId()?->value(),
            'toStatusId' => $event->toStatusId()?->value(),
            'actor' => $event->actor(),
            'summary' => $event->summary(),
            'createdAt' => $event->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<IssueEvent> $events
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $events): array
    {
        return array_map(fn(IssueEvent $event): array => $this->present($event), $events);
    }
}
