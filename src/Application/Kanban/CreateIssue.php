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
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventType;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Kanban\WorkdirKind;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;
use App\Domain\Vcs\GitRepositoryId;

final readonly class CreateIssue
{
    public function __construct(
        private IssueRepository $issues,
        private IssueTypeRepository $issueTypes,
        private StatusRepository $statuses,
        private FieldDefinitionRepository $fields,
        private IssueEventRepository $events,
        private IssueKeyGenerator $keys,
        private WorkdirRepository $workdirs,
        private GitRepositories $repositories,
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
        ?IssueKey $parentKey = null,
        ?GitRepositoryId $repositoryId = null,
    ): Issue {
        $this->guard->assertMember($actor, $workspaceId);
        $this->issueTypes->find($workspaceId, $issueTypeId) ?? throw NotFoundException::issueType();
        if ($parentKey !== null) {
            $this->guardParentDepth($workspaceId, $parentKey, $issueTypeId);
        }
        $status = $statusId === null
            ? $this->initialStatus($workspaceId, $issueTypeId)
            : $this->status($workspaceId, $issueTypeId, $statusId);
        $values = $this->validateFields->execute(
            $this->fields->forIssueType($workspaceId, $issueTypeId),
            $fieldValues,
        );

        $issueId = IssueId::generate();
        $key = $this->keys->next($workspaceId, $prefix);
        $workdirId = $this->resolveWorkdir($workspaceId, $issueId, $key, $parentKey, $repositoryId);
        $position = $this->nextPosition($workspaceId, $status->id());

        $issue = new Issue(
            $issueId,
            $key,
            $workspaceId,
            $issueTypeId,
            $status->id(),
            $summary,
            $description,
            $actor->login()->value(),
            $assigneeId,
            $values,
            workdirId: $workdirId,
            parentKey: $parentKey,
            position: $position,
        );
        $this->issues->add($issue);
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $workspaceId,
            $issue->id(),
            IssueEventType::Created,
            null,
            $status->id(),
            $actor->login()->value(),
        ));

        return $issue;
    }

    private function nextPosition(WorkspaceId $workspaceId, StatusId $statusId): int
    {
        $position = 0;
        foreach ($this->issues->forStatuses($workspaceId, new StatusIds([$statusId])) as $issue) {
            $position = max($position, $issue->position() + 1);
        }

        return $position;
    }

    /** The chain grows one level down at a time; the frontier is the deepest configured issue type. */
    private function guardParentDepth(WorkspaceId $workspaceId, IssueKey $parentKey, IssueTypeId $childTypeId): void
    {
        $parent = $this->issues->find($workspaceId, $parentKey) ?? throw NotFoundException::issue();
        $parentType = $this->issueTypes->find($workspaceId, $parent->issueTypeId()) ?? throw NotFoundException::issueType();
        $childType = $this->issueTypes->find($workspaceId, $childTypeId) ?? throw NotFoundException::issueType();
        if ($childType->level()->value() !== $parentType->level()->value() + 1) {
            throw new ValidationException(
                'issueTypeId',
                'The child issue type must sit exactly one level deeper than its parent.',
            );
        }
    }

    private function resolveWorkdir(
        WorkspaceId $workspaceId,
        IssueId $issueId,
        IssueKey $key,
        ?IssueKey $parentKey,
        ?GitRepositoryId $repositoryId,
    ): WorkdirId {
        if ($parentKey !== null) {
            $parent = $this->issues->find($workspaceId, $parentKey) ?? throw NotFoundException::issue();
            $inherited = $parent->workdirId();
            if ($inherited !== null) {
                return $inherited;
            }
        }

        $workdir = $repositoryId === null
            ? new Workdir(WorkdirId::generate(), $workspaceId, $issueId, WorkdirKind::Scratch)
            : $this->repositoryWorkdir($workspaceId, $issueId, $key, $repositoryId);
        $this->workdirs->add($workdir);

        return $workdir->id();
    }

    private function repositoryWorkdir(
        WorkspaceId $workspaceId,
        IssueId $issueId,
        IssueKey $key,
        GitRepositoryId $repositoryId,
    ): Workdir {
        $this->repositories->find($workspaceId, $repositoryId) ?? throw NotFoundException::repository();

        return new Workdir(
            WorkdirId::generate(),
            $workspaceId,
            $issueId,
            WorkdirKind::Repository,
            $repositoryId,
            'agent/' . $key->value(),
        );
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
