<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workdirs')]
#[ORM\Index(name: 'idx_workdirs_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_workdirs_owner_issue', columns: ['owner_issue_id'])]
final class WorkdirRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'owner_issue_id', type: 'string', length: 32)]
    private string $ownerIssueId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $kind;

    #[ORM\Column(type: 'string', length: 16)]
    private string $state;

    #[ORM\Column(name: 'repository_id', type: 'string', length: 32, nullable: true)]
    private ?string $repositoryId;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $branch;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $ownerIssueId,
        string $kind,
        string $state,
        ?string $repositoryId,
        ?string $branch,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->ownerIssueId = $ownerIssueId;
        $this->kind = $kind;
        $this->state = $state;
        $this->repositoryId = $repositoryId;
        $this->branch = $branch;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function ownerIssueId(): string
    {
        return $this->ownerIssueId;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function repositoryId(): ?string
    {
        return $this->repositoryId;
    }

    public function branch(): ?string
    {
        return $this->branch;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function sync(
        string $kind,
        string $state,
        ?string $repositoryId,
        ?string $branch,
        DateTimeImmutable $updatedAt,
    ): void {
        $this->kind = $kind;
        $this->state = $state;
        $this->repositoryId = $repositoryId;
        $this->branch = $branch;
        $this->updatedAt = $updatedAt;
    }
}
