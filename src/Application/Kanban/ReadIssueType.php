<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadIssueType
{
    public function __construct(
        private IssueTypeRepository $issueTypes,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, IssueTypeId $id): IssueType
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->issueTypes->find($workspaceId, $id) ?? throw NotFoundException::issueType();
    }
}
