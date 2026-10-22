<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadIssue
{
    public function __construct(
        private IssueRepository $issues,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, IssueKey $key): Issue
    {
        $this->guard->assertMember($actor, $workspaceId);

        return $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
    }
}
