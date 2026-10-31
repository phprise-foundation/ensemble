<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirState;
use App\Domain\Runner\AgentExecutor;
use App\Domain\Runner\AgentOutcome;
use App\Domain\Runner\AgentOutcomeType;
use App\Domain\Runner\AgentRunContext;
use App\Domain\Runner\Outcome;
use App\Domain\Runner\Run;
use App\Domain\Runner\RunId;
use App\Domain\Runner\RunState;
use App\Domain\Runner\WorkdirPaths;
use App\Domain\Runner\WorkdirVcs;
use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\Role;
use App\Domain\Team\RoleCapabilities;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\PullRequests;
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
        private PullRequests $pullRequests,
        private RoleRepository $roles,
        private ReportStage $report,
        private ReadAgentOutcome $outcomes,
    ) {
    }

    public function execute(): void
    {
        $now = new DateTimeImmutable();
        $this->reclaimExpired($now);
        $requested = $this->runRequested($now);

        foreach ($this->workspaces->all() as $workspace) {
            $this->sweepWorkspace($workspace->id(), $now, $requested);
            $this->advanceWaitingParents($workspace->id());
        }
    }

    /** @param list<string> $requestedIssues */
    private function sweepWorkspace(WorkspaceId $workspaceId, DateTimeImmutable $now, array $requestedIssues): void
    {
        foreach ($this->issues->forWorkspace($workspaceId) as $issue) {
            if ($issue->blocked() || $issue->readyToMerge() || in_array($issue->id()->value(), $requestedIssues, true)) {
                continue;
            }

            $status = $this->statuses->find($workspaceId, $issue->statusId());
            if ($status === null || $status->roleIds()->isEmpty()) {
                continue;
            }

            $roles = $this->stageRoles($workspaceId, $status);
            if ($roles === []) {
                continue;
            }

            $agent = $this->actorFor($workspaceId, $issue, $status);
            if ($agent === null) {
                continue;
            }

            $this->runIssue(
                $workspaceId,
                $agent,
                $issue,
                $status,
                RoleCapabilities::fromRoles($roles),
                $now,
            );
        }
    }

    /** @return list<Role> */
    private function stageRoles(WorkspaceId $workspaceId, Status $status): array
    {
        $roles = [];
        foreach ($status->roleIds()->all() as $roleId) {
            $role = $this->roles->find($workspaceId, $roleId);
            if ($role !== null) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    private function actorFor(WorkspaceId $workspaceId, Issue $issue, Status $status): ?Agent
    {
        $assigneeId = $issue->assigneeId();
        if ($assigneeId !== null) {
            $assigned = $this->agents->find($workspaceId, new AgentId($assigneeId));
            if ($assigned !== null && $assigned->active() && $this->hasStageRole($assigned, $status)) {
                return $assigned;
            }
        }

        foreach ($this->agents->forWorkspace($workspaceId) as $agent) {
            if (! $agent->active() || ! $this->hasStageRole($agent, $status)) {
                continue;
            }

            if ($issue->assigneeId() !== $agent->id()->value()) {
                $issue->assign($agent->id()->value());
                $this->issues->update($issue);
            }

            return $agent;
        }

        return null;
    }

    private function hasStageRole(Agent $agent, Status $status): bool
    {
        foreach ($agent->roleIds()->all() as $roleId) {
            if ($status->roleIds()->contains($roleId)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function runRequested(DateTimeImmutable $now): array
    {
        $processed = [];
        foreach ($this->runs->requested() as $run) {
            $processed[] = $run->issueId()->value();
            $this->executeRequested($run, $now);
        }

        return array_values(array_unique($processed));
    }

    private function executeRequested(Run $run, DateTimeImmutable $now): void
    {
        $agent = $this->agents->find($run->workspaceId(), $run->agentId());
        $issue = $this->issues->findById($run->workspaceId(), $run->issueId());
        if ($agent === null || $issue === null || ! $agent->active()) {
            $this->finishRequested($run, 'the requested run is no longer available', $now);

            return;
        }

        if ($issue->blocked()) {
            $this->finishRequested($run, 'the issue is blocked', $now);

            return;
        }

        if ($issue->readyToMerge()) {
            $this->finishRequested($run, 'the issue is ready to merge', $now);

            return;
        }

        $status = $this->statuses->find($run->workspaceId(), $issue->statusId());
        if ($status === null) {
            $this->finishRequested($run, 'the issue has no status', $now);

            return;
        }

        $roles = $this->stageRoles($run->workspaceId(), $status);
        if ($roles === []) {
            $this->finishRequested($run, 'the stage has no role with capabilities', $now);

            return;
        }

        $workdir = $this->workdirFor($issue);
        if ($workdir === null) {
            $this->finishRequested($run, 'the issue has no workdir', $now);

            return;
        }

        $reason = $this->prepare($workdir);
        if ($reason !== null) {
            $this->finishRequested($run, $reason, $now);

            return;
        }

        $this->executeRun(
            $run->workspaceId(),
            $agent,
            $issue,
            $status,
            $roles,
            RoleCapabilities::fromRoles($roles),
            $now,
            $run->attempt(),
            $run,
        );
    }

    private function finishRequested(Run $run, string $reason, DateTimeImmutable $now): void
    {
        $run->skip($reason, $now);
        $this->runs->update($run);
    }

    private function runIssue(
        WorkspaceId $workspaceId,
        Agent $agent,
        Issue $issue,
        Status $status,
        RoleCapabilities $capabilities,
        DateTimeImmutable $now,
    ): void {
        if ($this->runs->activeForIssue($issue->id()) !== null) {
            return;
        }

        $latest = $this->runs->latestFor($agent->id(), $issue->id());
        $attempt = 1;
        if ($latest instanceof Run && $latest->createdAt() >= $issue->enteredAt()) {
            if ($latest->state() === RunState::Succeeded) {
                return;
            }

            if ($latest->state() === RunState::Failed) {
                if ($latest->attempt() >= self::MAX_ATTEMPTS) {
                    return;
                }
                $attempt = $latest->attempt() + 1;
            }
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

        $this->executeRun($workspaceId, $agent, $issue, $status, $this->stageRoles($workspaceId, $status), $capabilities, $now, $attempt);
    }

    private function skip(
        WorkspaceId $workspaceId,
        Agent $agent,
        Issue $issue,
        string $reason,
        ?Run $latest,
        DateTimeImmutable $now,
    ): void {
        if (
            $latest instanceof Run
            && $latest->createdAt() >= $issue->enteredAt()
            && $latest->state() === RunState::Skipped
            && $latest->reason() === $reason
        ) {
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

    /** @param list<Role> $roles */
    private function executeRun(
        WorkspaceId $workspaceId,
        Agent $agent,
        Issue $issue,
        Status $status,
        array $roles,
        RoleCapabilities $capabilities,
        DateTimeImmutable $now,
        int $attempt,
        ?Run $requested = null,
    ): void {
        $workdir = $this->workdirFor($issue) ?? throw new RuntimeException('The bound workdir vanished.');
        $role = $this->promptRole($agent, $roles);
        $prompt = $this->prompts->compose($workspaceId, $agent, $issue, $status, $role, $capabilities);
        $run = $requested ?? new Run(
            RunId::generate(),
            $workspaceId,
            $agent->id(),
            $issue->id(),
            attempt: $attempt,
        );
        $workdirPath = $this->paths->ensure($workspaceId, $workdir->id());
        $outcomePath = $this->outcomePath($run);
        $run->start($prompt, $now, self::LEASE_SECONDS);
        if ($requested === null) {
            $this->runs->add($run);
        } else {
            $this->runs->update($run);
        }

        $context = new AgentRunContext(
            $outcomePath,
            $status->name(),
            $role->name(),
            $this->allowedOutcomes($capabilities),
        );
        $outcome = $this->executor->execute($agent, $workdirPath, $prompt, $context);
        $failure = null;
        if ($outcome->ok) {
            if ($capabilities->implement()) {
                $blocked = $this->outcomes->execute($outcomePath);
                if ($blocked->type() !== AgentOutcomeType::None && $blocked->type() !== AgentOutcomeType::Blocked) {
                    $outcome = Outcome::failed('the stage result is not allowed: ' . $blocked->type()->value);
                    $failure = ['invalid stage result', $blocked->reason()];
                } elseif ($blocked->type() === AgentOutcomeType::Blocked) {
                    $outcome = Outcome::failed('blocked: ' . $blocked->reason());
                    $failure = ['run blocked', $blocked->reason()];
                } else {
                    $published = $this->vcs->publish($workdir, $workdirPath);
                    if (! $published->ok) {
                        $outcome = Outcome::failed('the branch was not published: ' . $published->message);
                    } else {
                        $delivery = $this->pullRequests->open($workdir, $issue);
                        if ($delivery->isFailed()) {
                            $outcome = Outcome::failed($delivery->message());
                        } else {
                            if ($delivery->url() !== null) {
                                $run->attachPullRequest($delivery->url());
                            }
                            $this->advance->afterRun($agent, $issue);
                        }
                    }
                }
            } else {
                $discardFailure = $this->discardFailure($workdir, $workdirPath);
                [$outcome, $failure] = $this->stageResult(
                    $this->outcomes->execute($outcomePath),
                    $capabilities,
                    $agent,
                    $issue,
                    $workdir,
                );
                if ($discardFailure !== null && $outcome->ok) {
                    $outcome = Outcome::failed($discardFailure);
                }
            }
        } else {
            if (! $capabilities->implement()) {
                $discardFailure = $this->discardFailure($workdir, $workdirPath);
                if ($discardFailure !== null) {
                    $outcome = Outcome::failed($discardFailure);
                }
            }
        }

        @unlink($outcomePath);
        $run->complete($outcome, new DateTimeImmutable());
        $this->runs->update($run);

        if ($failure !== null) {
            $this->report->failed($issue, $failure[0], $failure[1]);

            return;
        }

        if (! $outcome->ok && $attempt >= self::MAX_ATTEMPTS) {
            $this->report->failed($issue, 'run failed after ' . $attempt . ' attempts', $outcome->message);
        }
    }

    /**
     * @return array{0: Outcome, 1: ?array{0: string, 1: string}}
     */
    private function stageResult(
        AgentOutcome $outcome,
        RoleCapabilities $capabilities,
        Agent $agent,
        Issue $issue,
        Workdir $workdir,
    ): array {
        $type = $outcome->type();
        $reason = $outcome->reason();

        if ($type === AgentOutcomeType::None) {
            return $capabilities->requiresOutcome()
                ? [Outcome::failed('no review/test result was reported'), ['missing stage result', 'The agent did not report a result with ens.']]
                : $this->advanceAfter($agent, $issue, 'stage done');
        }

        if (! in_array($type->value, $this->allowedOutcomes($capabilities), true)) {
            return [Outcome::failed('the stage result is not allowed: ' . $type->value), ['invalid stage result', $reason]];
        }

        return match ($type) {
            AgentOutcomeType::Blocked => [Outcome::failed('blocked: ' . $reason), ['run blocked', $reason]],
            AgentOutcomeType::TestFailed => [Outcome::failed('QA failed: ' . $reason), ['QA failed', $reason]],
            AgentOutcomeType::ChangesRequested => [Outcome::failed('review requested changes: ' . $reason), ['review requested changes', $reason]],
            AgentOutcomeType::TestPassed => $this->qaPassed($issue, $workdir),
            AgentOutcomeType::ReviewApproved => $this->advanceAfter($agent, $issue, 'review approved'),
            default => [Outcome::failed('invalid stage result'), ['invalid stage result', $reason]],
        };
    }

    private function discardFailure(Workdir $workdir, string $path): ?string
    {
        $discarded = $this->vcs->discard($workdir, $path);

        return $discarded->ok ? null : 'local changes were not discarded: ' . $discarded->message;
    }

    /** @return array{0: Outcome, 1: null} */
    private function qaPassed(Issue $issue, Workdir $workdir): array
    {
        $this->report->readyToMerge($issue, $workdir->branch()?->value() ?? 'the branch');

        return [Outcome::succeeded('QA passed; waiting for merge'), null];
    }

    /** @return array{0: Outcome, 1: null} */
    private function advanceAfter(Agent $agent, Issue $issue, string $message): array
    {
        $this->advance->afterRun($agent, $issue);

        return [Outcome::succeeded($message), null];
    }

    /** @param list<Role> $roles */
    private function promptRole(Agent $agent, array $roles): Role
    {
        foreach ($roles as $role) {
            if ($agent->roleIds()->contains($role->id())) {
                return $role;
            }
        }

        return $roles[0];
    }

    /** @return list<string> */
    private function allowedOutcomes(RoleCapabilities $capabilities): array
    {
        $allowed = ['blocked'];
        if ($capabilities->review()) {
            $allowed[] = 'review_approved';
            $allowed[] = 'changes_requested';
        }
        if ($capabilities->test()) {
            $allowed[] = 'test_passed';
            $allowed[] = 'test_failed';
        }

        return array_values(array_unique($allowed));
    }

    private function outcomePath(Run $run): string
    {
        $directory = sys_get_temp_dir() . '/ensemble-outcomes';
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $path = $directory . '/' . $run->id()->value() . '.json';
        @unlink($path);

        return $path;
    }

    /** The subtasks-done trigger: parents outside agent statuses move when their latest child activity allows. */
    private function advanceWaitingParents(WorkspaceId $workspaceId): void
    {
        $owned = $this->ownedStatusIds($workspaceId);
        foreach ($this->issues->forWorkspace($workspaceId) as $issue) {
            if ($issue->blocked() || in_array($issue->statusId()->value(), $owned, true)) {
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

    private function reclaimExpired(DateTimeImmutable $now): void
    {
        foreach ($this->runs->withExpiredLeases() as $run) {
            $run->reclaim($now, self::LEASE_SECONDS, self::MAX_ATTEMPTS);
            $this->runs->update($run);
        }
    }
}
