<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueEventType;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class UpdateIssue
{
    public function __construct(
        private IssueRepository $issues,
        private FieldDefinitionRepository $fields,
        private ValidateFieldValues $validateFields,
        private IssueEventRepository $events,
        private AgentRepository $agents,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @param array<string, mixed> $fieldValues */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        IssueKey $key,
        ?string $summary,
        ?string $description,
        bool $assigneeProvided,
        ?string $assigneeId,
        bool $blockedProvided,
        ?bool $blocked,
        bool $fieldsProvided,
        array $fieldValues,
        bool $readyToMergeProvided = false,
        ?bool $readyToMerge = null,
    ): Issue {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        $previousAssignee = $issue->assigneeId();
        $summaryChanged = $summary !== null && $summary !== $issue->summary();
        $descriptionChanged = $description !== null && $description !== $issue->description();
        $assigneeChanged = $assigneeProvided && $assigneeId !== $previousAssignee;

        if ($summaryChanged) {
            $issue->rename($summary);
        }
        if ($descriptionChanged) {
            $issue->describe($description);
        }
        if ($assigneeProvided) {
            $issue->assign($assigneeId);
        }
        if ($blockedProvided && $blocked !== null) {
            $issue->block($blocked);
        }
        if ($readyToMergeProvided && $readyToMerge !== null) {
            $readyToMerge ? $issue->markReadyToMerge() : $issue->clearReadyToMerge();
        }
        if ($fieldsProvided) {
            $issue->changeFieldValues($this->validateFields->execute(
                $this->fields->forIssueType($workspaceId, $issue->issueTypeId()),
                $fieldValues,
            ));
        }
        $issue->touch(new DateTimeImmutable());
        $this->issues->update($issue);

        $actorLogin = $actor->login()->value();
        if ($summaryChanged) {
            $this->record($workspaceId, $issue->id(), IssueEventType::Summary, $actorLogin, $summary);
        }
        if ($descriptionChanged) {
            $this->record($workspaceId, $issue->id(), IssueEventType::Description, $actorLogin, 'Description updated');
        }
        if ($assigneeChanged) {
            $this->record(
                $workspaceId,
                $issue->id(),
                IssueEventType::Assignee,
                $actorLogin,
                sprintf(
                    '%s → %s',
                    $this->assigneeLabel($workspaceId, $previousAssignee),
                    $this->assigneeLabel($workspaceId, $assigneeId),
                ),
            );
        }

        return $issue;
    }

    private function record(
        WorkspaceId $workspaceId,
        IssueId $issueId,
        IssueEventType $type,
        string $actor,
        ?string $summary,
    ): void {
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $workspaceId,
            $issueId,
            $type,
            null,
            null,
            $actor,
            $summary,
        ));
    }

    private function assigneeLabel(WorkspaceId $workspaceId, ?string $assigneeId): string
    {
        if ($assigneeId === null || $assigneeId === '') {
            return 'Unassigned';
        }

        try {
            $agent = $this->agents->find($workspaceId, new AgentId($assigneeId));
        } catch (InvalidArgumentException) {
            return $assigneeId;
        }

        return $agent?->name() ?? $assigneeId;
    }
}
