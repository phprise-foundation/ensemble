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
use App\Domain\Kanban\IssueEventType;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusIds;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\TransitionActor;
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

    /** Moves the issue through its single agent transition once a published run ends. */
    public function afterRun(Agent $agent, Issue $issue): void
    {
        $target = $this->nextTarget($issue);
        if ($target === null) {
            return;
        }

        $this->move($issue, $target, $agent->name());
    }

    /** The subtasks-done trigger: moves a waiting parent since a child reached its final status. */
    public function waiting(Issue $issue): void
    {
        $target = $this->nextTarget($issue);
        if ($target === null) {
            return;
        }

        $children = $this->issues->forChildren($issue->workspaceId(), $issue->key());
        if ($children === [] || ! $this->rearmed($issue, $children)) {
            return;
        }

        $this->move($issue, $target, 'runner');
    }

    /**
     * Re-arms only when a child has activity newer than the parent's last change,
     * so a parent cannot keep crawling forward without fresh subtask completions.
     * @param list<Issue> $children
     */
    private function rearmed(Issue $issue, array $children): bool
    {
        foreach ($children as $child) {
            if ($issue->updatedAt() < $child->updatedAt()) {
                return true;
            }
        }

        return false;
    }

    private function nextTarget(Issue $issue): ?Status
    {
        $current = $this->statuses->find($issue->workspaceId(), $issue->statusId());
        if ($current === null) {
            throw NotFoundException::status();
        }

        $targets = [];
        foreach ($this->transitions->forWorkflow($issue->workspaceId(), $current->workflowId()) as $transition) {
            if ($transition->fromStatusId()->value() !== $issue->statusId()->value()) {
                continue;
            }

            if ($this->agentCan($transition->actor())) {
                $targets[$transition->toStatusId()->value()] = $transition->toStatusId();
            }
        }

        if (count($targets) !== 1) {
            return null;
        }

        $target = $this->statuses->find($issue->workspaceId(), $targets[array_key_first($targets)]);
        if ($target === null) {
            throw NotFoundException::status();
        }

        if ($this->hasUndoneSubtasks($issue)) {
            return null;
        }

        return $target;
    }

    private function agentCan(TransitionActor $actor): bool
    {
        return $actor === TransitionActor::Agent || $actor === TransitionActor::Both;
    }

    private function hasUndoneSubtasks(Issue $issue): bool
    {
        $children = $this->issues->forChildren($issue->workspaceId(), $issue->key());
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

    private function move(Issue $issue, Status $target, string $actor): void
    {
        $at = new DateTimeImmutable();
        $previous = $issue->statusId();
        $issue->moveTo($target->id(), $at);
        $issue->reposition(count($this->issues->forStatuses($issue->workspaceId(), new StatusIds([$target->id()]))));
        $this->issues->update($issue);
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $issue->workspaceId(),
            $issue->id(),
            IssueEventType::Status,
            $previous,
            $target->id(),
            $actor,
            null,
            $at,
        ));
    }
}
