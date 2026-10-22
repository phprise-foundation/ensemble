<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListIssueEvents
{
    public function __construct(
        private IssueRepository $issues,
        private IssueEventRepository $events,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<IssueEvent> */
    public function execute(User $actor, WorkspaceId $workspaceId, IssueKey $key): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();

        return $this->events->forIssue($workspaceId, $issue->id());
    }
}
