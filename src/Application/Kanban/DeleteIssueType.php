<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteIssueType
{
    public function __construct(
        private IssueTypeRepository $issueTypes,
        private FieldDefinitionRepository $fields,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, IssueTypeId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $issueType = $this->issueTypes->find($workspaceId, $id) ?? throw NotFoundException::issueType();
        if ($this->fields->countForIssueType($id) > 0) {
            throw ConflictException::inUse('issue type');
        }

        $this->issueTypes->delete($issueType);
    }
}
