<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Runner;

use App\Domain\Kanban\IssueId;
use App\Domain\Runner\Outcome;
use App\Domain\Runner\Run;
use App\Domain\Runner\RunId;
use App\Domain\Runner\RunState;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunTest extends TestCase
{
    private WorkspaceId $workspaceId;

    protected function setUp(): void
    {
        $this->workspaceId = new WorkspaceId(str_repeat('a', 32));
    }

    public function testClaimedRunSkipsWithReason(): void
    {
        $run = new Run(
            RunId::generate(),
            $this->workspaceId,
            AgentId::generate(),
            IssueId::generate(),
        );

        $now = new DateTimeImmutable();
        $run->skip('workdir is not bound', $now);

        self::assertSame('skipped', $run->state()->value);
        self::assertSame('workdir is not bound', $run->reason());
        self::assertNotNull($run->finishedAt());
    }

    public function testRunStartsAndSucceeds(): void
    {
        $run = new Run(
            RunId::generate(),
            $this->workspaceId,
            AgentId::generate(),
            IssueId::generate(),
        );

        $run->start('do the work', new DateTimeImmutable(), 1800);
        self::assertTrue($run->state()->isActive());
        self::assertSame('do the work', $run->prompt());

        $run->complete(Outcome::succeeded('changes pushed'), new DateTimeImmutable());
        self::assertSame('succeeded', $run->state()->value);
        self::assertSame('changes pushed', $run->message());
        self::assertNull($run->reason());
        self::assertNull($run->leaseExpiresAt());
    }

    public function testFailedRunRecordsTheReason(): void
    {
        $run = new Run(
            RunId::generate(),
            $this->workspaceId,
            AgentId::generate(),
            IssueId::generate(),
        );

        $run->start('p', new DateTimeImmutable(), 600);
        $run->complete(Outcome::failed('git push rejected'), new DateTimeImmutable());

        self::assertSame('failed', $run->state()->value);
        self::assertSame('git push rejected', $run->reason());
    }

    public function testReclaimIncrementsUntilTheCeiling(): void
    {
        $run = new Run(
            RunId::generate(),
            $this->workspaceId,
            AgentId::generate(),
            IssueId::generate(),
            leaseExpiresAt: new DateTimeImmutable('-1 hour'),
        );

        $now = new DateTimeImmutable();
        $run->start('p', $now, 1);
        self::assertSame(1, $run->attempt());

        $run->reclaim($now->modify('+10 seconds'), 1800, 3);
        self::assertTrue($run->state()->isActive());
        self::assertSame(2, $run->attempt());

        $run->reclaim($run->leaseExpiresAt()->modify('+10 seconds'), 1800, 3);
        $run->reclaim($run->leaseExpiresAt()->modify('+10 seconds'), 1800, 3);
        self::assertSame('failed', $run->state()->value);
        self::assertSame('the run lost its lease too many times', $run->reason());
    }

    public function testRunningRunRequiresStartAndLease(): void
    {
        $this->expectException(RuntimeException::class);

        new Run(
            RunId::generate(),
            $this->workspaceId,
            AgentId::generate(),
            IssueId::generate(),
            RunState::Running,
        );
    }
}
