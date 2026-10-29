<?php

declare(strict_types=1);

namespace App\Tests\Integration\Runner;

use App\Application\Runner\AdvanceIssue;
use App\Application\Runner\ExecuteSweep;
use App\Application\Runner\ResolveWorkdirConflict;
use App\Application\Runner\PromptComposer;
use App\Application\Runner\ResolveProfile;
use App\Domain\Runner\AgentExecutor;
use App\Domain\Kanban\FieldValues;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueTypeRepository;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\Level;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Kanban\WorkdirKind;
use App\Domain\Kanban\WorkdirState;
use App\Domain\Runner\Outcome;
use App\Domain\Runner\Run;
use App\Domain\Runner\RunId;
use App\Domain\Runner\RunState;
use App\Domain\Runner\WorkdirPaths;
use App\Domain\Runner\WorkdirVcs;
use App\Domain\Runner\ResolutionStrategy;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\Role;
use App\Domain\Team\RoleIds;
use App\Domain\Team\RoleId;
use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\Login;
use App\Domain\Security\PasswordHash;
use App\Infrastructure\Persistence\Doctrine\Mapper\MembershipRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineMembershipRepository;
use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;
use App\Domain\Vcs\GitRepositoryId;
use App\Infrastructure\Persistence\Doctrine\DoctrineEntityManagerFactory;
use App\Infrastructure\Persistence\Doctrine\DoctrineIssueKeyGenerator;
use App\Infrastructure\Persistence\Doctrine\Mapper\AgentRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueTypeRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueEventRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\ProfileRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\TransitionRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\RunRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\StatusRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\TeamRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkdirRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkflowRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkspaceRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineAgentRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueEventRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueTypeRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineProfileRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineRunRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineStatusRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineTeamRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineTransitionRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineWorkflowRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineWorkdirRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineWorkspaceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\TestCase;
use Throwable;

final class RunnerSweepTest extends TestCase
{
    private DoctrineIssueRepository $issues;
    private DoctrineWorkdirRepository $workdirs;
    private DoctrineRunRepository $runs;
    private DoctrineAgentRepository $agents;
    private DoctrineStatusRepository $statuses;
    private DoctrineProfileRepository $profiles;
    private DoctrineWorkspaceRepository $workspaces;
    private DoctrineEntityManagerFactory $factory;
    private RecordedExecutor $executor;
    private RecordedVcs $vcs;
    private WorkdirPathsForTest $paths;
    private WorkspaceId $workspaceId;
    private WorkdirId $workdirId;
    private EntityManagerInterface $entityManager;
    private StatusId $inboxId;
    private StatusId $refinedId;
    private StatusId $doneId;
    private StatusId $testingId;
    private WorkflowId $workflowId;
    private IssueTypeId $issueTypeId;
    private Issue $issue;
    private Agent $agent;

    protected function setUp(): void
    {
        try {
            $this->entityManager = DoctrineEntityManagerFactory::fromEnvironment(true)->recreateSchema();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }

        $this->factory = DoctrineEntityManagerFactory::fromEnvironment(true);
        $this->paths = new WorkdirPathsForTest();
        $this->vcs = new RecordedVcs();
        $this->executor = new RecordedExecutor();
        $this->workspaceId = new WorkspaceId(bin2hex(random_bytes(16)));

        $workflowRepository = new DoctrineWorkflowRepository($this->entityManager, new WorkflowRecordMapper());
        $issueTypes = new DoctrineIssueTypeRepository($this->entityManager, new IssueTypeRecordMapper());
        $teams = new DoctrineTeamRepository($this->entityManager, new TeamRecordMapper());
        $this->statuses = new DoctrineStatusRepository($this->entityManager, new StatusRecordMapper());
        $this->workdirs = new DoctrineWorkdirRepository($this->entityManager, new WorkdirRecordMapper());
        $this->issues = new DoctrineIssueRepository($this->entityManager, new IssueRecordMapper());
        $this->runs = new DoctrineRunRepository($this->entityManager, new RunRecordMapper());
        $this->agents = new DoctrineAgentRepository($this->entityManager, new AgentRecordMapper());
        $this->profiles = new DoctrineProfileRepository($this->entityManager, new ProfileRecordMapper());
        $this->workspaces = new DoctrineWorkspaceRepository($this->entityManager, new WorkspaceRecordMapper());

        $this->workspaces->add(new Workspace($this->workspaceId, 'Dev', new WorkspacePrefix('KAN')));
        $workflow = new Workflow(WorkflowId::generate(), $this->workspaceId, Slug::fromName('Delivery'), 'Delivery');
        $workflowRepository->add($workflow);
        $issueType = new IssueType(
            IssueTypeId::generate(),
            $this->workspaceId,
            $workflow->id(),
            Slug::fromName('Task'),
            'Task',
            new Level(1),
        );
        $issueTypes->add($issueType);
        $this->workflowId = $workflow->id();
        $this->issueTypeId = $issueType->id();
        $team = new Team(TeamId::generate(), $this->workspaceId, 'Squad');
        $teams->add($team);
        $role = new Role(RoleId::generate(), $this->workspaceId, $team->id(), 'Coder');
        $status = new Status(
            StatusId::generate(),
            $this->workspaceId,
            $workflow->id(),
            $issueType->id(),
            Slug::fromName('Inbox'),
            'Inbox',
            StatusType::Initial,
            '#4c9aff',
            new Position(10),
            null,
            null,
            new RoleIds([$role->id()]),
        );
        $this->statuses->add($status);
        $this->inboxId = $status->id();
        $refined = new Status(
            StatusId::generate(),
            $this->workspaceId,
            $workflow->id(),
            $issueType->id(),
            Slug::fromName('Refined'),
            'Refined',
            StatusType::Transitional,
            '#36b37e',
            new Position(20),
        );
        $this->statuses->add($refined);
        $this->refinedId = $refined->id();
        $done = new Status(
            StatusId::generate(),
            $this->workspaceId,
            $workflow->id(),
            $issueType->id(),
            Slug::fromName('Done'),
            'Done',
            StatusType::Final,
            '#6b778c',
            new Position(30),
        );
        $this->statuses->add($done);
        $this->doneId = $done->id();
        $testing = new Status(
            StatusId::generate(),
            $this->workspaceId,
            $workflow->id(),
            $issueType->id(),
            Slug::fromName('Testing'),
            'Testing',
            StatusType::Transitional,
            '#ffab00',
            new Position(40),
        );
        $this->statuses->add($testing);
        $this->testingId = $testing->id();

        $this->issue = new Issue(
            IssueId::generate(),
            new IssueKey('KAN-1'),
            $this->workspaceId,
            $issueType->id(),
            $status->id(),
            'Ship the runner',
            'Make the runner execute work.',
            'admin',
            null,
            new FieldValues(),
        );
        $workdir = new Workdir(WorkdirId::generate(), $this->workspaceId, $this->issue->id(), WorkdirKind::Scratch);
        $this->issue->assignWorkdir($workdir->id());
        $this->issues->add($this->issue);
        $this->workdirs->add($workdir);
        $this->workdirId = $workdir->id();

        $this->agent = new Agent(AgentId::generate(), $this->workspaceId, $team->id(), 'Kaio', 'A pragmatic builder.');
        $this->agent->assignRoles(new RoleIds([$role->id()]));
        $this->agents->add($this->agent);
    }

    public function testSweepVisiblySkipsWhenUnboundAndRunsWhenBound(): void
    {
        $this->sweep();
        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(1, $runs);
        self::assertSame('skipped', $runs[0]->state()->value);
        self::assertSame('the issue workdir is not bound yet (scratch)', $runs[0]->reason());
        self::assertSame([], $this->executor->executions);

        $this->sweep();
        self::assertCount(1, $this->runs->forIssue($this->workspaceId, $this->issue->id()));

        $this->bindWorkdir();
        $this->sweep();
        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(2, $runs);
        $executed = $runs[1];
        self::assertSame('succeeded', $executed->state()->value);
        self::assertNotNull($executed->prompt());
        self::assertStringContainsString('Ship the runner', (string) $executed->prompt());
        self::assertSame($this->paths->path($this->workspaceId, $this->workdirId), $this->executor->executions[0]);
    }

    public function testExpiredLeasesReclaimOncePerSweep(): void
    {
        $run = new Run(
            RunId::generate(),
            $this->workspaceId,
            $this->agent->id(),
            $this->issue->id(),
            RunState::Claimed,
            null,
            2,
            new DateTimeImmutable('-10 seconds'),
        );
        $this->runs->add($run);

        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(1, $runs);
        self::assertSame(3, $runs[0]->attempt());
        self::assertSame('claimed', $runs[0]->state()->value);
    }

    public function testSucceededRunAdvancesTheIssueThroughTheAgentTransition(): void
    {
        $this->seedTransition($this->inboxId, $this->refinedId, TransitionActor::Agent);
        $this->attachPending();
        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertSame('succeeded', $runs[0]->state()->value);
        self::assertSame(
            $this->refinedId->value(),
            $this->issues->forWorkspace($this->workspaceId)[0]->statusId()->value(),
        );
    }

    public function testAdvancementWaitsWhileUndoneSubtasksRemain(): void
    {
        $this->seedTransition($this->inboxId, $this->refinedId, TransitionActor::Agent);
        $this->attachPending();
        $this->seedChildIssue('KAN-2');
        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertSame('succeeded', $runs[0]->state()->value);
        self::assertSame(
            $this->inboxId->value(),
            $this->issues->forWorkspace($this->workspaceId)[0]->statusId()->value(),
        );
    }

    public function testWaitingParentAdvancesWhenTheLastSubtaskIsDone(): void
    {
        $this->seedTransition($this->inboxId, $this->refinedId, TransitionActor::Agent);
        $this->seedTransition($this->refinedId, $this->testingId, TransitionActor::Agent);
        $this->seedChildIssue('KAN-2', $this->doneId);
        $this->attachPending();
        $this->sweep();

        self::assertSame(
            $this->refinedId->value(),
            $this->issues->forWorkspace($this->workspaceId)[0]->statusId()->value(),
        );

        $child = $this->issues->find($this->workspaceId, new IssueKey('KAN-2')) ?? self::fail('child vanished');
        $child->touch(new DateTimeImmutable('+1 second'));
        $this->issues->update($child);
        $this->sweep();

        self::assertSame(
            $this->testingId->value(),
            $this->issues->forWorkspace($this->workspaceId)[0]->statusId()->value(),
        );
    }

    public function testWaitingParentDoesNotCrawlForwardWithoutNewChildActivity(): void
    {
        $this->seedTransition($this->refinedId, $this->testingId, TransitionActor::Agent);
        $this->seedTransition($this->testingId, $this->doneId, TransitionActor::Agent);
        $this->seedChildIssue('KAN-2', $this->doneId);
        $this->sweepWithParentRefined();
        $this->sweep();

        self::assertSame(
            $this->testingId->value(),
            $this->issues->forWorkspace($this->workspaceId)[0]->statusId()->value(),
        );
    }

    public function testRequestedRunRetriesAfterAFailedRun(): void
    {
        $this->attachPending();
        $failed = new Run(RunId::generate(), $this->workspaceId, $this->agent->id(), $this->issue->id());
        $failed->start('old prompt', new DateTimeImmutable('-5 minutes'), 1800);
        $failed->complete(Outcome::failed('boom'), new DateTimeImmutable('-4 minutes'));
        $this->runs->add($failed);

        $this->runs->add(new Run(
            RunId::generate(),
            $this->workspaceId,
            $this->agent->id(),
            $this->issue->id(),
        ));

        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(2, $runs);
        self::assertSame('failed', $runs[0]->state()->value);
        self::assertSame('succeeded', $runs[1]->state()->value);
        self::assertCount(1, $this->executor->executions);
    }

    public function testRequestedRunSkipsWhenTheWorkdirIsScratch(): void
    {
        $this->runs->add(new Run(
            RunId::generate(),
            $this->workspaceId,
            $this->agent->id(),
            $this->issue->id(),
        ));

        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(1, $runs);
        self::assertSame('skipped', $runs[0]->state()->value);
        self::assertSame('the issue workdir is not bound yet (scratch)', $runs[0]->reason());
        self::assertCount(0, $this->executor->executions);
    }

    public function testFailsAtTheReclaimCeiling(): void
    {
        $run = new Run(
            RunId::generate(),
            $this->workspaceId,
            $this->agent->id(),
            $this->issue->id(),
            RunState::Claimed,
            null,
            3,
            new DateTimeImmutable('-10 seconds'),
        );
        $this->runs->add($run);

        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(1, $runs);
        self::assertSame('failed', $runs[0]->state()->value);
        self::assertSame('the run lost its lease too many times', $runs[0]->reason());
    }

    private function sweep(): void
    {
        (new ExecuteSweep(
            $this->workspaces,
            $this->agents,
            $this->statuses,
            $this->issues,
            $this->workdirs,
            $this->vcs,
            $this->runs,
            $this->paths,
            $this->executor,
            new PromptComposer(new ResolveProfile($this->profiles)),
            new AdvanceIssue(
                $this->issues,
                $this->statuses,
                new DoctrineTransitionRepository($this->entityManager, new TransitionRecordMapper()),
                new DoctrineIssueEventRepository($this->entityManager, new IssueEventRecordMapper()),
            ),
        ))->execute();
    }

    public function testResolvesAConflictingWorkdirBackToBound(): void
    {
        $this->attachPending();
        $this->vcs->prepareFailure = 'git clone failed: boom';
        $this->sweep();
        $this->vcs->prepareFailure = null;
        $workdir = $this->workdirs->find($this->workspaceId, $this->workdirId) ?? self::fail('the workdir vanished');

        $guard = new WorkspaceGuard(
            $this->workspaces,
            new DoctrineMembershipRepository($this->entityManager, new MembershipRecordMapper()),
        );
        $resolver = new ResolveWorkdirConflict($this->issues, $this->workdirs, $this->paths, $this->vcs, $guard);
        $resolver->execute($this->admin(), $this->workspaceId, $this->issue->key(), 'merge');

        self::assertSame(
            WorkdirState::Bound,
            $this->workdirs->find($this->workspaceId, $this->workdirId)?->state(),
        );
        self::assertSame(['merge'], $this->vcs->resolved);
    }

    private function admin(): User
    {
        return new User(
            UserId::generate(),
            new Login('runner'),
            new PasswordHash('svp-hash'),
            'Runner',
            admin: true,
        );
    }

    private function seedTransition(StatusId $from, StatusId $to, TransitionActor $actor): void
    {
        (new DoctrineTransitionRepository($this->entityManager, new TransitionRecordMapper()))->add(
            new Transition(TransitionId::generate(), $this->workspaceId, $this->workflowId, $from, $to, $actor),
        );
    }

    private function seedChildIssue(string $key, ?StatusId $statusId = null): Issue
    {
        $child = new Issue(
            IssueId::generate(),
            new IssueKey($key),
            $this->workspaceId,
            $this->issueTypeId,
            $statusId ?? $this->inboxId,
            'Subtask of the runner',
            'A child issue blocking the parent.',
            'admin',
            null,
            new FieldValues(),
            null,
            null,
            $this->issue->key(),
        );
        $this->issues->add($child);

        return $child;
    }

    /** Places the unbound parent directly in Refined, as a finished run would. */
    private function sweepWithParentRefined(): void
    {
        $parent = $this->issues->find($this->workspaceId, $this->issue->key())
            ?? self::fail('the issue vanished');
        $parent->moveTo($this->refinedId, new DateTimeImmutable('-1 minute'));
        $this->issues->update($parent);
        $this->sweep();
    }

    private function bindWorkdir(): void
    {
        $bound = new Workdir(
            $this->workdirId,
            $this->workspaceId,
            $this->issue->id(),
            WorkdirKind::Repository,
            GitRepositoryId::generate(),
            'agent/KAN-1',
            WorkdirState::Bound,
        );
        $this->workdirs->update($bound);
    }

    private function attachPending(): void
    {
        $this->workdirs->update(new Workdir(
            $this->workdirId,
            $this->workspaceId,
            $this->issue->id(),
            WorkdirKind::Repository,
            GitRepositoryId::generate(),
            'agent/KAN-1',
            WorkdirState::Pending,
        ));
    }

    public function testPendingWorkdirIsPreparedThenRun(): void
    {
        $this->attachPending();
        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(1, $runs);
        self::assertSame('succeeded', $runs[0]->state()->value);
        self::assertNotNull($this->workdirs->find($this->workspaceId, $this->workdirId)?->state());
        self::assertSame('bound', $this->workdirs->find($this->workspaceId, $this->workdirId)->state()->value);
        self::assertSame([$this->paths->path($this->workspaceId, $this->workdirId)], $this->vcs->prepared);
        self::assertSame([$this->paths->path($this->workspaceId, $this->workdirId)], $this->executor->executions);
    }

    public function testFailedPreparationConflictsTheWorkdirWithVisibleReason(): void
    {
        $this->attachPending();
        $this->vcs->prepareFailure = 'not a git repository';
        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertCount(1, $runs);
        self::assertSame('skipped', $runs[0]->state()->value);
        self::assertSame('workdir preparation failed: not a git repository', $runs[0]->reason());
        self::assertSame('conflict', $this->workdirs->find($this->workspaceId, $this->workdirId)->state()->value);
        self::assertSame([], $this->executor->executions);

        $this->vcs->prepareFailure = null;
        $this->sweep();
        self::assertSame([], $this->executor->executions);
        self::assertSame('conflict', $this->workdirs->find($this->workspaceId, $this->workdirId)->state()->value);
        self::assertSame(
            'the issue workdir is in conflict',
            $this->runs->forIssue($this->workspaceId, $this->issue->id())[1]->reason(),
        );
    }

    public function testPublishFailureFailsTheRunWithReason(): void
    {
        $this->attachPending();
        $this->vcs->publishFailure = 'remote rejected';
        $this->sweep();

        $runs = $this->runs->forIssue($this->workspaceId, $this->issue->id());
        self::assertSame('failed', $runs[0]->state()->value);
        self::assertSame('the branch was not published: remote rejected', $runs[0]->reason());
    }
}

final class RecordedVcs implements WorkdirVcs
{
    /** @var list<string> */
    public array $prepared = [];

    /** @var list<string> */
    public array $published = [];

    /** @var list<string> */
    public array $resolved = [];

    public ?string $prepareFailure = null;

    public ?string $publishFailure = null;

    public function __construct()
    {
    }

    #[Override]
    public function prepare(Workdir $workdir, string $path): Outcome
    {
        $this->prepared[] = $path;
        if ($this->prepareFailure !== null) {
            return Outcome::failed($this->prepareFailure);
        }

        return Outcome::succeeded('cloned');
    }

    #[Override]
    public function publish(Workdir $workdir, string $path): Outcome
    {
        if ($this->publishFailure !== null) {
            return Outcome::failed($this->publishFailure);
        }

        $this->published[] = $path;

        return Outcome::succeeded('pushed ' . $workdir->branch()->value());
    }

    #[Override]
    public function resolve(Workdir $workdir, string $path, ResolutionStrategy $strategy): Outcome
    {
        $this->resolved[] = $strategy->value;

        return Outcome::succeeded('resolved with ' . $strategy->value);
    }
}

final class RecordedExecutor implements AgentExecutor
{
    /** @var list<string> */
    public array $executions = [];

    #[Override]
    public function execute(Agent $agent, string $workdirPath, string $prompt): Outcome
    {
        $this->executions[] = $workdirPath;

        return Outcome::succeeded('changes pushed');
    }
}

final class WorkdirPathsForTest implements WorkdirPaths
{
    #[Override]
    public function ensure(WorkspaceId $workspaceId, WorkdirId $workdirId): string
    {
        return $this->path($workspaceId, $workdirId);
    }

    #[Override]
    public function path(WorkspaceId $workspaceId, WorkdirId $workdirId): string
    {
        return 'var/workdirs/' . $workspaceId->value() . '/' . $workdirId->value();
    }
}
