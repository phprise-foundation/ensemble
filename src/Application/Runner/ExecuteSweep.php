<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirState;
use App\Domain\Runner\AgentExecutor;
use App\Domain\Runner\Outcome;
use App\Domain\Runner\Run;
use App\Domain\Runner\RunId;
use App\Domain\Runner\RunState;
use App\Domain\Runner\WorkdirPaths;
use App\Domain\Runner\WorkdirVcs;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Team\Agent;
use DateTimeImmutable;
use RuntimeException;

final readonly class ExecuteSweep
{
    private const LEASE_SECONDS = 1800;

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private WorkspaceRepository $workspaces,
        private AgentRepository $agents,
        private StatusRepository $statuses,
        private IssueRepository $issues,
        private WorkdirRepository $workdirs,
        private WorkdirVcs $vcs,
        private RunRepository $runs,
        private WorkdirPaths $paths,
        private AgentExecutor $executor,
        private PromptComposer $prompts,
        private AdvanceIssue $advance,
    ) {
    }

    public function execute(): void
    {
        $now = new DateTimeImmutable();
        $this->reclaimExpired($now);

        foreach ($this->workspaces->all() as $workspace) {
            foreach ($this->agents->forWorkspace($workspace->id()) as $agent) {
                if ($agent->active()) {
                    $this->agentSweep($workspace->id(), $agent, $now);
                }
            }

            $this->advanceWaitingParents($workspace->id());
        }
    }

    /** The subtasks-done trigger: parents outside agent statuses move when their latest child activity allows. */
    private function advanceWaitingParents(WorkspaceId $workspaceId): void
    {
        $owned = $this->ownedStatusIds($workspaceId);
        foreach ($this->issues->forWorkspace($workspaceId) as $issue) {
            if (in_array($issue->statusId()->value(), $owned, true)) {
                continue;
            }

            if ($this->isFinal($issue->workspaceId(), $issue->statusId())) {
                continue;
            }

            $this->advance->waiting($issue);
        }
    }

    private function isFinal(WorkspaceId $workspaceId, StatusId $statusId): bool
    {
        $status = $this->statuses->find($workspaceId, $statusId);
        if ($status === null) {
            return false;
        }

        return $status->type() === StatusType::Final;
    }

    /** @return list<string> */
    private function ownedStatusIds(WorkspaceId $workspaceId): array
    {
        $ids = [];
        foreach ($this->agents->forWorkspace($workspaceId) as $agent) {
            if (! $agent->active()) {
                continue;
            }

            foreach ($this->statuses->ownedByRoles($workspaceId, $agent->roleIds()) as $status) {
                $ids[$status->id()->value()] = $status->id()->value();
            }
        }

        return array_values($ids);
    }

    private function agentSweep(WorkspaceId $workspaceId, Agent $agent, DateTimeImmutable $now): void
    {
        if ($agent->roleIds()->isEmpty()) {
            return;
        }

        $statuses = $this->statuses->ownedByRoles($workspaceId, $agent->roleIds());
        if ($statuses === []) {
            return;
        }

        $issues = $this->issues->forStatuses($workspaceId, new StatusIds(array_map(
            fn(Status $status): StatusId => $status->id(),
            $statuses,
        )));
        foreach ($issues as $issue) {
            $this->runIssue($workspaceId, $agent, $issue, $now);
        }
    }

    private function runIssue(WorkspaceId $workspaceId, Agent $agent, Issue $issue, DateTimeImmutable $now): void
    {
        if ($this->runs->activeForIssue($issue->id()) !== null) {
            return;
        }

        $latest = $this->runs->latestFor($agent->id(), $issue->id());
        if ($latest instanceof Run && in_array($latest->state(), [RunState::Succeeded, RunState::Failed], true)) {
            return;
        }

        $workdir = $this->workdirFor($issue);
        if ($workdir === null) {
            $this->skip($workspaceId, $agent, $issue, 'the issue has no workdir', $latest, $now);

            return;
        }

        $reason = $this->prepare($workdir);
        if ($reason !== null) {
            $this->skip($workspaceId, $agent, $issue, $reason, $latest, $now);

            return;
        }

        $this->executeRun($workspaceId, $agent, $issue, $now);
    }

    private function skip(
        WorkspaceId $workspaceId,
        Agent $agent,
        Issue $issue,
        string $reason,
        ?Run $latest,
        DateTimeImmutable $now,
    ): void {
        if ($latest instanceof Run && $latest->state() === RunState::Skipped && $latest->reason() === $reason) {
            return;
        }

        $run = new Run(RunId::generate(), $workspaceId, $agent->id(), $issue->id());
        $run->skip($reason, $now);
        $this->runs->add($run);
    }

    /** Null means the workdir is ready; otherwise the reason to show on the skip run. */
    private function prepare(Workdir $workdir): ?string
    {
        return match ($workdir->state()) {
            WorkdirState::Bound => null,
            WorkdirState::Pending => $this->preparePending($workdir),
            WorkdirState::Conflict => 'the issue workdir is in conflict',
            WorkdirState::Scratch => 'the issue workdir is not bound yet (scratch)',
        };
    }

    private function preparePending(Workdir $workdir): ?string
    {
        $path = $this->paths->ensure($workdir->workspaceId(), $workdir->id());
        $prepared = $this->vcs->prepare($workdir, $path);
        if ($prepared->ok) {
            $workdir->markBound();
            $this->workdirs->update($workdir);

            return null;
        }

        $workdir->markConflict();
        $this->workdirs->update($workdir);

        return 'workdir preparation failed: ' . $prepared->message;
    }

    private function workdirFor(Issue $issue): ?Workdir
    {
        return $issue->workdirId() === null
            ? null
            : $this->workdirs->find($issue->workspaceId(), $issue->workdirId());
    }

    private function executeRun(WorkspaceId $workspaceId, Agent $agent, Issue $issue, DateTimeImmutable $now): void
    {
        $workdir = $this->workdirFor($issue) ?? throw new RuntimeException('The bound workdir vanished.');
        $run = new Run(RunId::generate(), $workspaceId, $agent->id(), $issue->id());
        $workdirPath = $this->paths->ensure($workspaceId, $workdir->id());
        $prompt = $this->prompts->compose($workspaceId, $agent, $issue);
        $run->start($prompt, $now, self::LEASE_SECONDS);
        $this->runs->add($run);

        $outcome = $this->executor->execute($agent, $workdirPath, $prompt);
        if ($outcome->ok) {
            $published = $this->vcs->publish($workdir, $workdirPath);
            if (! $published->ok) {
                $outcome = Outcome::failed('the branch was not published: ' . $published->message);
            } else {
                $this->advance->afterRun($agent, $issue);
            }
        }

        $run->complete($outcome, new DateTimeImmutable());
        $this->runs->update($run);
    }

    private function reclaimExpired(DateTimeImmutable $now): void
    {
        foreach ($this->runs->withExpiredLeases() as $run) {
            $run->reclaim($now, self::LEASE_SECONDS, self::MAX_ATTEMPTS);
            $this->runs->update($run);
        }
    }
}
