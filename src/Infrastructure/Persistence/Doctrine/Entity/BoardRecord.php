<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'boards')]
#[ORM\UniqueConstraint(name: 'uniq_boards_workspace_key', columns: ['workspace_id', 'key'])]
#[ORM\Index(name: 'idx_boards_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_boards_workflow', columns: ['workflow_id'])]
final class BoardRecord
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

    /** @var list<string> */
    #[ORM\Column(name: 'filter_issue_type_ids', type: 'json')]
    private array $filterIssueTypeIds;

    /** @var list<string> */
    #[ORM\Column(name: 'filter_status_ids', type: 'json')]
    private array $filterStatusIds;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param list<string> $filterIssueTypeIds
     * @param list<string> $filterStatusIds
     */
    public function __construct(
        string $id,
        string $workspaceId,
        string $workflowId,
        string $key,
        string $name,
        array $filterIssueTypeIds,
        array $filterStatusIds,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->workflowId = $workflowId;
        $this->key = $key;
        $this->name = $name;
        $this->filterIssueTypeIds = $filterIssueTypeIds;
        $this->filterStatusIds = $filterStatusIds;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): string { return $this->id; }
    public function workspaceId(): string { return $this->workspaceId; }
    public function workflowId(): string { return $this->workflowId; }
    public function key(): string { return $this->key; }
    public function name(): string { return $this->name; }
    public function filterIssueTypeIds(): array { return $this->filterIssueTypeIds; }
    public function filterStatusIds(): array { return $this->filterStatusIds; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    /**
     * @param list<string> $filterIssueTypeIds
     * @param list<string> $filterStatusIds
     */
    public function sync(string $name, array $filterIssueTypeIds, array $filterStatusIds, DateTimeImmutable $updatedAt): void
    {
        $this->name = $name;
        $this->filterIssueTypeIds = $filterIssueTypeIds;
        $this->filterStatusIds = $filterStatusIds;
        $this->updatedAt = $updatedAt;
    }
}
