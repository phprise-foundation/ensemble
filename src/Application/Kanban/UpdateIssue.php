<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateIssue
{
    public function __construct(
        private IssueRepository $issues,
        private FieldDefinitionRepository $fields,
        private ValidateFieldValues $validateFields,
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
    ): Issue {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        if ($summary !== null) {
            $issue->rename($summary);
        }
        if ($description !== null) {
            $issue->describe($description);
        }
        if ($assigneeProvided) {
            $issue->assign($assigneeId);
        }
        if ($blockedProvided && $blocked !== null) {
            $issue->block($blocked);
        }
        if ($fieldsProvided) {
            $issue->changeFieldValues($this->validateFields->execute(
                $this->fields->forIssueType($workspaceId, $issue->issueTypeId()),
                $fieldValues,
            ));
        }
        $issue->touch(new DateTimeImmutable());
        $this->issues->update($issue);

        return $issue;
    }
}
