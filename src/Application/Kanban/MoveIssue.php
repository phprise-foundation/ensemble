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
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
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

    public function execute(User $actor, WorkspaceId $workspaceId, IssueKey $key, StatusId $targetId): Issue
    {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        $target = $this->statuses->find($workspaceId, $targetId) ?? throw NotFoundException::status();
        if ($target->issueTypeId()->value() !== $issue->issueTypeId()->value()) {
            throw new ValidationException('statusId', 'Status does not belong to the issue type.');
        }
        if ($target->id()->value() === $issue->statusId()->value()) {
            return $issue;
        }
        $this->guardTransition($issue, $target->workflowId(), $target);
        $at = new DateTimeImmutable();
        $previous = $issue->statusId();
        $issue->moveTo($target->id(), $at);
        $this->issues->update($issue);
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $workspaceId,
            $issue->id(),
            $previous,
            $target->id(),
            $actor->login()->value(),
            $at,
        ));

        return $issue;
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
