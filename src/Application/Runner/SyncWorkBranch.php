<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirState;
use App\Domain\Runner\BranchSyncStrategy;
use App\Domain\Runner\WorkdirPaths;
use App\Domain\Runner\WorkdirVcs;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class SyncWorkBranch
{
    public function __construct(
        private IssueRepository $issues,
        private WorkdirRepository $workdirs,
        private WorkdirPaths $paths,
        private WorkdirVcs $vcs,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $user, WorkspaceId $workspaceId, IssueKey $key, string $strategyRaw): Workdir
    {
        $this->guard->assertManager($user, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        if (! $issue->readyToMerge()) {
            throw new ValidationException('readyToMerge', 'Only an issue ready to merge can sync its work branch.');
        }

        $workdirId = $issue->workdirId() ?? throw new ValidationException('workdir', 'The issue has no workdir.');
        $workdir = $this->workdirs->find($workspaceId, $workdirId) ?? throw NotFoundException::workdir();
        if ($workdir->state() !== WorkdirState::Bound) {
            throw new ValidationException('workdir', 'Only a bound workdir can sync its work branch.');
        }

        $strategy = BranchSyncStrategy::fromString($strategyRaw);
        $result = $this->vcs->sync($workdir, $this->paths->ensure($workspaceId, $workdirId), $strategy);
        if (! $result->ok) {
            throw ConflictException::mergeFailed($result->message);
        }

        return $workdir;
    }
}
