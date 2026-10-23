<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\BaseBranch;
use App\Domain\Vcs\GitRepositoryId;
use DateTimeImmutable;

final class Workdir
{
    private WorkdirKind $kind;
    private WorkdirState $state;
    private ?GitRepositoryId $repositoryId;
    private ?BaseBranch $branch;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly WorkdirId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly IssueId $ownerIssueId,
        WorkdirKind $kind = WorkdirKind::Scratch,
        ?GitRepositoryId $repositoryId = null,
        ?string $branch = null,
        ?WorkdirState $state = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        if ($kind === WorkdirKind::Scratch && ($repositoryId !== null || $branch !== null)) {
            throw new ValidationException('workdir', 'A scratch workdir cannot carry a repository or a branch.');
        }
        if ($kind === WorkdirKind::Repository && ($repositoryId === null || $branch === null)) {
            throw new ValidationException('workdir', 'A repository workdir requires a repository and a branch.');
        }
        $this->kind = $kind;
        $this->repositoryId = $repositoryId;
        $this->branch = $branch === null ? null : new BaseBranch($branch);
        $this->state = $state ?? ($kind === WorkdirKind::Scratch ? WorkdirState::Scratch : WorkdirState::Pending);
        if ($this->kind === WorkdirKind::Scratch && $this->state !== WorkdirState::Scratch) {
            throw new ValidationException('workdir', 'A scratch workdir is always in the scratch state.');
        }
        if ($this->kind === WorkdirKind::Repository && $this->state === WorkdirState::Scratch) {
            throw new ValidationException('workdir', 'A repository workdir cannot be in the scratch state.');
        }
        $this->updatedAt = $updatedAt;
    }

    public function id(): WorkdirId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function ownerIssueId(): IssueId
    {
        return $this->ownerIssueId;
    }

    public function kind(): WorkdirKind
    {
        return $this->kind;
    }

    public function state(): WorkdirState
    {
        return $this->state;
    }

    public function repositoryId(): ?GitRepositoryId
    {
        return $this->repositoryId;
    }

    public function branch(): ?BaseBranch
    {
        return $this->branch;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function bind(GitRepositoryId $repositoryId, BaseBranch $branch): void
    {
        if ($this->kind === WorkdirKind::Repository) {
            throw ConflictException::workdirBound();
        }

        $this->kind = WorkdirKind::Repository;
        $this->repositoryId = $repositoryId;
        $this->branch = $branch;
        $this->state = WorkdirState::Pending;
        $this->touch(new DateTimeImmutable());
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }
}
