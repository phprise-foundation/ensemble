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
use App\Domain\Kanban\WorkdirState;
use App\Domain\Runner\AgentExecutor;
use App\Domain\Runner\Run;
use App\Domain\Runner\RunId;
use App\Domain\Runner\RunState;
use App\Domain\Runner\WorkdirPaths;
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
        private RunRepository $runs,
        private WorkdirPaths $paths,
        private AgentExecutor $executor,
        private PromptComposer $prompts,
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
        }
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

        $reason = $this->blockReason($issue);
        if ($reason === null) {
            $this->executeRun($workspaceId, $agent, $issue, $now);

            return;
        }

        if ($latest instanceof Run && $latest->state() === RunState::Skipped && $latest->reason() === $reason) {
            return;
        }

        $run = new Run(RunId::generate(), $workspaceId, $agent->id(), $issue->id());
        $run->skip($reason, $now);
        $this->runs->add($run);
    }

    private function blockReason(Issue $issue): ?string
    {
        if ($issue->workdirId() === null) {
            return 'the issue has no workdir';
        }

        $workdir = $this->workdirs->find($issue->workspaceId(), $issue->workdirId());
        if ($workdir === null) {
            return 'the issue workdir vanished';
        }

        if ($workdir->state() !== WorkdirState::Bound) {
            return 'the issue workdir is not bound yet (' . $workdir->state()->value . ')';
        }

        return null;
    }

    private function executeRun(WorkspaceId $workspaceId, Agent $agent, Issue $issue, DateTimeImmutable $now): void
    {
        $workdirId = $issue->workdirId();
        if ($workdirId === null) {
            return;
        }

        $workdir = $this->workdirs->find($workspaceId, $workdirId) ?? throw new RuntimeException('The bound workdir vanished.');
        $run = new Run(RunId::generate(), $workspaceId, $agent->id(), $issue->id());
        $workdirPath = $this->paths->ensure($workspaceId, $workdir->id());
        $prompt = $this->prompts->compose($workspaceId, $agent, $issue);
        $run->start($prompt, $now, self::LEASE_SECONDS);
        $this->runs->add($run);

        $outcome = $this->executor->execute($agent, $workdirPath, $prompt);
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
