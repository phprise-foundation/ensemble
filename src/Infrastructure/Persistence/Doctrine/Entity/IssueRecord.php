<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'issues')]
#[ORM\UniqueConstraint(name: 'uniq_issues_workspace_key', columns: ['workspace_id', 'key'])]
#[ORM\Index(name: 'idx_issues_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_issues_status', columns: ['status_id'])]
#[ORM\Index(name: 'idx_issues_type', columns: ['issue_type_id'])]
final class IssueRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $key;

    #[ORM\Column(name: 'issue_type_id', type: 'string', length: 32)]
    private string $issueTypeId;

    #[ORM\Column(name: 'status_id', type: 'string', length: 32)]
    private string $statusId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $summary;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 64)]
    private string $reporter;

    #[ORM\Column(name: 'assignee_id', type: 'string', length: 64, nullable: true)]
    private ?string $assigneeId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $worker;

    #[ORM\Column(type: 'boolean')]
    private bool $blocked;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'field_values', type: 'json')]
    private array $fieldValues;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'entered_at', type: 'datetime_immutable')]
    private DateTimeImmutable $enteredAt;

    /** @param array<string, mixed> $fieldValues */
    public function __construct(
        string $id,
        string $workspaceId,
        string $key,
        string $issueTypeId,
        string $statusId,
        string $summary,
        string $description,
        string $reporter,
        ?string $assigneeId,
        string $worker,
        bool $blocked,
        array $fieldValues,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        DateTimeImmutable $enteredAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->key = $key;
        $this->issueTypeId = $issueTypeId;
        $this->statusId = $statusId;
        $this->summary = $summary;
        $this->description = $description;
        $this->reporter = $reporter;
        $this->assigneeId = $assigneeId;
        $this->worker = $worker;
        $this->blocked = $blocked;
        $this->fieldValues = $fieldValues;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->enteredAt = $enteredAt;
    }

    public function id(): string { return $this->id; }
    public function workspaceId(): string { return $this->workspaceId; }
    public function key(): string { return $this->key; }
    public function issueTypeId(): string { return $this->issueTypeId; }
    public function statusId(): string { return $this->statusId; }
    public function summary(): string { return $this->summary; }
    public function description(): string { return $this->description; }
    public function reporter(): string { return $this->reporter; }
    public function assigneeId(): ?string { return $this->assigneeId; }
    public function worker(): string { return $this->worker; }
    public function blocked(): bool { return $this->blocked; }
    /** @return array<string, mixed> */
    public function fieldValues(): array { return $this->fieldValues; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function enteredAt(): DateTimeImmutable { return $this->enteredAt; }

    /** @param array<string, mixed> $fieldValues */
    public function sync(
        string $statusId,
        string $summary,
        string $description,
        ?string $assigneeId,
        string $worker,
        bool $blocked,
        array $fieldValues,
        DateTimeImmutable $updatedAt,
        DateTimeImmutable $enteredAt,
    ): void {
        $this->statusId = $statusId;
        $this->summary = $summary;
        $this->description = $description;
        $this->assigneeId = $assigneeId;
        $this->worker = $worker;
        $this->blocked = $blocked;
        $this->fieldValues = $fieldValues;
        $this->updatedAt = $updatedAt;
        $this->enteredAt = $enteredAt;
    }
}
