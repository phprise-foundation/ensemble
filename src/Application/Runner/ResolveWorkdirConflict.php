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
use App\Domain\Runner\ResolutionStrategy;
use App\Domain\Runner\WorkdirPaths;
use App\Domain\Runner\WorkdirVcs;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ResolveWorkdirConflict
{
    public function __construct(
        private IssueRepository $issues,
        private WorkdirRepository $workdirs,
        private WorkdirPaths $paths,
        private WorkdirVcs $vcs,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return Workdir the reconciled workdir, now bound. */
    public function execute(User $user, WorkspaceId $workspaceId, IssueKey $key, string $strategyRaw): Workdir
    {
        $this->guard->assertMember($user, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        $workdirId = $issue->workdirId() ?? throw new ValidationException('workdir', 'The issue has no workdir.');
        $workdir = $this->workdirs->find($workspaceId, $workdirId) ?? throw NotFoundException::workdir();
        if ($workdir->state() !== WorkdirState::Conflict) {
            throw new ValidationException('workdir', 'Only a conflicting workdir can be resolved.');
        }

        $strategy = ResolutionStrategy::fromString($strategyRaw);
        $resolved = $this->vcs->resolve($workdir, $this->paths->ensure($workspaceId, $workdirId), $strategy);
        if (! $resolved->ok) {
            throw ConflictException::mergeFailed($resolved->message);
        }

        $workdir->markBound();
        $this->workdirs->update($workdir);

        return $workdir;
    }
}
