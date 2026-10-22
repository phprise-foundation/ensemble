<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'issue_types')]
#[ORM\UniqueConstraint(name: 'uniq_issue_types_workflow_key', columns: ['workflow_id', 'key'])]
#[ORM\Index(name: 'idx_issue_types_workflow', columns: ['workflow_id'])]
#[ORM\Index(name: 'idx_issue_types_workspace', columns: ['workspace_id'])]
final class IssueTypeRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'workflow_id', type: 'string', length: 32)]
    private string $workflowId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $key;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $level;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $workflowId,
        string $key,
        string $name,
        int $level,
        int $position,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->workflowId = $workflowId;
        $this->key = $key;
        $this->name = $name;
        $this->level = $level;
        $this->position = $position;
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

    public function workflowId(): string
    {
        return $this->workflowId;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function sync(string $name, int $level, int $position, DateTimeImmutable $updatedAt): void
    {
        $this->name = $name;
        $this->level = $level;
        $this->position = $position;
        $this->updatedAt = $updatedAt;
    }
}
