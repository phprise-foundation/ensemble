<?php

declare(strict_types=1);

namespace App\Tests\Integration\Runner;

use App\Application\Runner\ExecuteSweep;
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
use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\Role;
use App\Domain\Team\RoleIds;
use App\Domain\Team\RoleId;
use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;
use App\Domain\Vcs\GitRepositoryId;
use App\Infrastructure\Persistence\Doctrine\DoctrineEntityManagerFactory;
use App\Infrastructure\Persistence\Doctrine\DoctrineIssueKeyGenerator;
use App\Infrastructure\Persistence\Doctrine\Mapper\AgentRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueTypeRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\ProfileRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\RunRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\StatusRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\TeamRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkdirRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkflowRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkspaceRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineAgentRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueTypeRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineProfileRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineRunRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineStatusRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineTeamRepository;
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
    private WorkdirPathsForTest $paths;
    private WorkspaceId $workspaceId;
    private WorkdirId $workdirId;
    private Issue $issue;
    private Agent $agent;

    protected function setUp(): void
    {
        try {
            $entityManager = DoctrineEntityManagerFactory::fromEnvironment(true)->recreateSchema();
        } catch (Throwable $error) {
            self::markTestSkipped('PostgreSQL test database unavailable: ' . $error->getMessage());
        }

        $this->factory = DoctrineEntityManagerFactory::fromEnvironment(true);
        $this->paths = new WorkdirPathsForTest();
        $this->executor = new RecordedExecutor();
        $this->workspaceId = new WorkspaceId(bin2hex(random_bytes(16)));

        $workflowRepository = new DoctrineWorkflowRepository($entityManager, new WorkflowRecordMapper());
        $issueTypes = new DoctrineIssueTypeRepository($entityManager, new IssueTypeRecordMapper());
        $teams = new DoctrineTeamRepository($entityManager, new TeamRecordMapper());
        $this->statuses = new DoctrineStatusRepository($entityManager, new StatusRecordMapper());
        $this->workdirs = new DoctrineWorkdirRepository($entityManager, new WorkdirRecordMapper());
        $this->issues = new DoctrineIssueRepository($entityManager, new IssueRecordMapper());
        $this->runs = new DoctrineRunRepository($entityManager, new RunRecordMapper());
        $this->agents = new DoctrineAgentRepository($entityManager, new AgentRecordMapper());
        $this->profiles = new DoctrineProfileRepository($entityManager, new ProfileRecordMapper());
        $this->workspaces = new DoctrineWorkspaceRepository($entityManager, new WorkspaceRecordMapper());

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
            $this->runs,
            $this->paths,
            $this->executor,
            new PromptComposer(new ResolveProfile($this->profiles)),
        ))->execute();
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
