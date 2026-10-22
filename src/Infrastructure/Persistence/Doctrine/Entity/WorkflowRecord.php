<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workflows')]
#[ORM\UniqueConstraint(name: 'uniq_workflows_workspace_key', columns: ['workspace_id', 'key'])]
#[ORM\Index(name: 'idx_workflows_workspace', columns: ['workspace_id'])]
final class WorkflowRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $key;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $key,
        string $name,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->key = $key;
        $this->name = $name;
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

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function sync(string $name, DateTimeImmutable $updatedAt): void
    {
        $this->name = $name;
        $this->updatedAt = $updatedAt;
    }
}
