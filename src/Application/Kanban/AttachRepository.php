<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Kanban\WorkdirKind;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\BaseBranch;
use App\Domain\Vcs\GitRepositoryId;

final readonly class AttachRepository
{
    public function __construct(
        private IssueRepository $issues,
        private WorkdirRepository $workdirs,
        private GitRepositories $repositories,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        IssueKey $key,
        GitRepositoryId $repositoryId,
    ): Workdir {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        $this->repositories->find($workspaceId, $repositoryId) ?? throw NotFoundException::repository();
        $workdir = $this->workdirFor($workspaceId, $issue);
        if ($workdir->kind() === WorkdirKind::Repository) {
            if ($workdir->repositoryId()?->equals($repositoryId) === true) {
                return $workdir;
            }

            throw ConflictException::workdirBound();
        }

        $root = $this->issues->findById($workspaceId, $workdir->ownerIssueId()) ?? throw NotFoundException::issue();
        $workdir->bind($repositoryId, new BaseBranch('agent/' . $root->key()->value()));
        $this->workdirs->update($workdir);

        return $workdir;
    }

    private function workdirFor(WorkspaceId $workspaceId, Issue $issue): Workdir
    {
        $existing = $issue->workdirId();
        if ($existing !== null) {
            return $this->workdirs->find($workspaceId, $existing) ?? throw NotFoundException::issue();
        }

        $workdir = new Workdir(WorkdirId::generate(), $workspaceId, $issue->id(), WorkdirKind::Scratch);
        $this->workdirs->add($workdir);
        $issue->assignWorkdir($workdir->id());
        $this->issues->update($issue);

        return $workdir;
    }
}
