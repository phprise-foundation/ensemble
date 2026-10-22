<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Contract\Kanban\IssueKeyGenerator;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;

final readonly class CreateIssue
{
    public function __construct(
        private IssueRepository $issues,
        private IssueTypeRepository $issueTypes,
        private StatusRepository $statuses,
        private FieldDefinitionRepository $fields,
        private IssueEventRepository $events,
        private IssueKeyGenerator $keys,
        private ValidateFieldValues $validateFields,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @param array<string, mixed> $fieldValues */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        WorkspacePrefix $prefix,
        IssueTypeId $issueTypeId,
        ?StatusId $statusId,
        string $summary,
        string $description,
        ?string $assigneeId,
        array $fieldValues,
    ): Issue {
        $this->guard->assertMember($actor, $workspaceId);
        $this->issueTypes->find($workspaceId, $issueTypeId) ?? throw NotFoundException::issueType();
        $status = $statusId === null
            ? $this->initialStatus($workspaceId, $issueTypeId)
            : $this->status($workspaceId, $issueTypeId, $statusId);
        $values = $this->validateFields->execute(
            $this->fields->forIssueType($workspaceId, $issueTypeId),
            $fieldValues,
        );

        $issue = new Issue(
            IssueId::generate(),
            $this->keys->next($workspaceId, $prefix),
            $workspaceId,
            $issueTypeId,
            $status->id(),
            $summary,
            $description,
            $actor->login()->value(),
            $assigneeId,
            $values,
        );
        $this->issues->add($issue);
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $workspaceId,
            $issue->id(),
            null,
            $status->id(),
            $actor->login()->value(),
        ));

        return $issue;
    }

    private function initialStatus(WorkspaceId $workspaceId, IssueTypeId $issueTypeId): Status
    {
        foreach ($this->statuses->forIssueType($workspaceId, $issueTypeId) as $status) {
            if ($status->isInitial()) {
                return $status;
            }
        }

        throw new ValidationException('statusId', 'Issue type has no initial status.');
    }

    private function status(WorkspaceId $workspaceId, IssueTypeId $issueTypeId, StatusId $statusId): Status
    {
        $status = $this->statuses->find($workspaceId, $statusId) ?? throw NotFoundException::status();
        if ($status->issueTypeId()->value() !== $issueTypeId->value()) {
            throw new ValidationException('statusId', 'Status does not belong to the issue type.');
        }

        return $status;
    }
}
