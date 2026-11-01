<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventType;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class MoveIssue
{
    public function __construct(
        private IssueRepository $issues,
        private StatusRepository $statuses,
        private TransitionRepository $transitions,
        private IssueEventRepository $events,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        IssueKey $key,
        StatusId $targetId,
        ?int $position = null,
    ): Issue {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        $target = $this->statuses->find($workspaceId, $targetId) ?? throw NotFoundException::status();
        if ($target->issueTypeId()->value() !== $issue->issueTypeId()->value()) {
            throw new ValidationException('statusId', 'Status does not belong to the issue type.');
        }
        $previous = $issue->statusId();
        $sameStatus = $target->id()->value() === $issue->statusId()->value();
        if ($sameStatus && $position === null) {
            return $issue;
        }
        if (!$sameStatus) {
            $this->guardTransition($issue, $target->workflowId(), $target);
            $at = new DateTimeImmutable();
            $issue->clearReadyToMerge();
            $issue->moveTo($target->id(), $at);
            $this->issues->update($issue);
            $this->events->add(new IssueEvent(
                IssueEventId::generate(),
                $workspaceId,
                $issue->id(),
                IssueEventType::Status,
                $previous,
                $target->id(),
                $actor->login()->value(),
                null,
                $at,
            ));
            $this->reindex($workspaceId, $previous);
        }
        $this->place($workspaceId, $target->id(), $issue, $position);

        return $issue;
    }

    private function place(WorkspaceId $workspaceId, StatusId $statusId, Issue $issue, ?int $index): void
    {
        $others = array_values(array_filter(
            $this->issues->forStatuses($workspaceId, new StatusIds([$statusId])),
            static fn(Issue $candidate): bool => $candidate->id()->value() !== $issue->id()->value(),
        ));
        $target = $index === null ? count($others) : max(0, min($index, count($others)));
        array_splice($others, $target, 0, [$issue]);
        $this->reposition($others);
    }

    private function reindex(WorkspaceId $workspaceId, StatusId $statusId): void
    {
        $this->reposition($this->issues->forStatuses($workspaceId, new StatusIds([$statusId])));
    }

    /** @param list<Issue> $issues */
    private function reposition(array $issues): void
    {
        foreach ($issues as $position => $candidate) {
            if ($candidate->position() === $position) {
                continue;
            }
            $candidate->reposition($position);
            $this->issues->update($candidate);
        }
    }

    public function assertCanMove(Issue $issue, Status $target): void
    {
        $this->guardTransition($issue, $target->workflowId(), $target);
    }

    private function guardTransition(Issue $issue, WorkflowId $workflowId, Status $target): void
    {
        foreach ($this->transitions->forWorkflow($issue->workspaceId(), $workflowId) as $transition) {
            if ($transition->fromStatusId()->value() !== $issue->statusId()->value()) {
                continue;
            }
            if ($transition->toStatusId()->value() !== $target->id()->value()) {
                continue;
            }
            if ($transition->actor() === TransitionActor::User || $transition->actor() === TransitionActor::Both) {
                return;
            }
        }

        throw ConflictException::transitionNotAllowed();
    }
}
