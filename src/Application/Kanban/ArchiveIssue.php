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
use DateTimeImmutable;

final readonly class ArchiveIssue
{
    public function __construct(
        private IssueRepository $issues,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, IssueKey $key, bool $archived): Issue
    {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        $now = new DateTimeImmutable();
        if ($archived) {
            $issue->archive($now);
        } else {
            $issue->restoreArchive($now);
        }
        $this->issues->update($issue);

        return $issue;
    }
}
