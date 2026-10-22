<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueCommentRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueComment;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListIssueComments
{
    public function __construct(
        private IssueRepository $issues,
        private IssueCommentRepository $comments,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<IssueComment> */
    public function execute(User $actor, WorkspaceId $workspaceId, IssueKey $key): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();

        return $this->comments->forIssue($workspaceId, $issue->id());
    }
}
