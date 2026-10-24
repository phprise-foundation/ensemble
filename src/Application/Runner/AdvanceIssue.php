<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Team\Agent;
use DateTimeImmutable;

final readonly class AdvanceIssue
{
    public function __construct(
        private IssueRepository $issues,
        private StatusRepository $statuses,
        private TransitionRepository $transitions,
        private IssueEventRepository $events,
    ) {
    }

    /** Advances the issue through its single agent transition when the subtask gate permits. */
    public function execute(Agent $agent, Issue $issue): void
    {
        $target = $this->nextTarget($issue);
        if ($target === null) {
            return;
        }

        $at = new DateTimeImmutable();
        $previous = $issue->statusId();
        $issue->moveTo($target->id(), $at);
        $this->issues->update($issue);
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $issue->workspaceId(),
            $issue->id(),
            $previous,
            $target->id(),
            $agent->name(),
            $at,
        ));


    }

    private function nextTarget(Issue $issue): ?Status
    {
        $current = $this->statuses->find($issue->workspaceId(), $issue->statusId());
        if ($current === null) {
            throw NotFoundException::status();
        }

        $targets = [];
        foreach ($this->transitions->forWorkflow($issue->workspaceId(), $current->workflowId()) as $transition) {            if ($transition->fromStatusId()->value() !== $issue->statusId()->value()) {
                continue;
            }

            if ($transition->actor() === TransitionActor::Agent || $transition->actor() === TransitionActor::Both) {
                $targets[$transition->toStatusId()->value()] = $transition->toStatusId();
            }
        }

        if (count($targets) !== 1) {
            return null;
        }

        $statusId = $targets[array_key_first($targets)];
        $target = $this->statuses->find($issue->workspaceId(), $statusId);
        if ($target === null) {
            throw NotFoundException::status();
        }

        if ($this->hasUndoneSubtasks($issue)) {
            return null;
        }

        return $target;
    }

    private function hasUndoneSubtasks(Issue $issue): bool
    {
        $children = $this->issues->forChildren($issue->workspaceId(), $issue->key());
        if ($children === []) {
            return false;
        }

        foreach ($children as $child) {
            if ($child->statusId()->value() === $issue->statusId()->value()) {
                return true;
            }

            $status = $this->statuses->find($issue->workspaceId(), $child->statusId());
            if ($status === null || $status->type() !== StatusType::Final) {
                return true;
            }
        }

        return false;
    }
}
