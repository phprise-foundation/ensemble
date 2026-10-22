<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListFieldDefinitions
{
    public function __construct(
        private IssueTypeRepository $issueTypes,
        private FieldDefinitionRepository $fields,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<FieldDefinition> */
    public function execute(User $actor, WorkspaceId $workspaceId, IssueTypeId $issueTypeId): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $this->issueTypes->find($workspaceId, $issueTypeId) ?? throw NotFoundException::issueType();

        return $this->fields->forIssueType($workspaceId, $issueTypeId);
    }
}
