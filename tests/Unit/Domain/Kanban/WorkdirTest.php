<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Kanban;

use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Kanban\WorkdirKind;
use App\Domain\Kanban\WorkdirState;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\BaseBranch;
use App\Domain\Vcs\GitRepositoryId;
use PHPUnit\Framework\TestCase;

final class WorkdirTest extends TestCase
{
    private IssueId $issueId;

    private WorkspaceId $workspaceId;

    protected function setUp(): void
    {
        $this->issueId = IssueId::generate();
        $this->workspaceId = new WorkspaceId(str_repeat('a', 32));
    }

    public function testStartsAsScratch(): void
    {
        $workdir = new Workdir(WorkdirId::generate(), $this->workspaceId, $this->issueId);

        self::assertSame(WorkdirKind::Scratch, $workdir->kind());
        self::assertSame(WorkdirState::Scratch, $workdir->state());
        self::assertNull($workdir->repositoryId());
        self::assertNull($workdir->branch());
        self::assertTrue($workdir->ownerIssueId()->equals($this->issueId));
    }

    public function testScratchWorkdirCannotCarryARepository(): void
    {
        $this->expectException(ValidationException::class);

        new Workdir(
            WorkdirId::generate(),
            $this->workspaceId,
            $this->issueId,
            WorkdirKind::Scratch,
            GitRepositoryId::generate(),
        );
    }

    public function testRepositoryWorkdirRequiresABranch(): void
    {
        $this->expectException(ValidationException::class);

        new Workdir(
            WorkdirId::generate(),
            $this->workspaceId,
            $this->issueId,
            WorkdirKind::Repository,
            GitRepositoryId::generate(),
        );
    }

    public function testBindsScratchToRepository(): void
    {
        $workdir = new Workdir(WorkdirId::generate(), $this->workspaceId, $this->issueId);
        $repositoryId = GitRepositoryId::generate();
        $workdir->bind($repositoryId, new BaseBranch('agent/ALP-1'));

        self::assertSame(WorkdirKind::Repository, $workdir->kind());
        self::assertSame(WorkdirState::Pending, $workdir->state());
        self::assertTrue($workdir->repositoryId()?->equals($repositoryId));
        self::assertSame('agent/ALP-1', $workdir->branch()?->value());
    }

    public function testCannotRebindABoundWorkdir(): void
    {
        $workdir = new Workdir(
            WorkdirId::generate(),
            $this->workspaceId,
            $this->issueId,
            WorkdirKind::Repository,
            GitRepositoryId::generate(),
            'agent/ALP-1',
        );

        $this->expectException(ConflictException::class);
        $workdir->bind(GitRepositoryId::generate(), new BaseBranch('agent/ALP-2'));
    }
}
