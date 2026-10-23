<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Issue
{
    private StringObject $summary;
    private StringObject $description;
    private StringObject $reporter;
    private ?string $assigneeId;
    private string $worker;
    private bool $blocked;
    private FieldValues $fieldValues;
    private StatusId $statusId;
    private ?WorkdirId $workdirId;
    private DateTimeImmutable $updatedAt;
    private DateTimeImmutable $enteredAt;

    public function __construct(
        private readonly IssueId $id,
        private readonly IssueKey $key,
        private readonly WorkspaceId $workspaceId,
        private readonly IssueTypeId $issueTypeId,
        StatusId $statusId,
        string $summary,
        string $description,
        string $reporter,
        ?string $assigneeId = null,
        FieldValues $fieldValues = new FieldValues(),
        private readonly ?DateTimeImmutable $createdAt = null,
        ?WorkdirId $workdirId = null,
        private readonly ?IssueKey $parentKey = null,
    ) {
        $this->summary = new StringObject($this->validSummary($summary));
        $this->description = new StringObject($this->validDescription($description));
        $this->reporter = new StringObject($this->validReporter($reporter));
        $this->assigneeId = $this->validAssignee($assigneeId);
        $this->worker = '';
        $this->blocked = false;
        $this->fieldValues = $fieldValues;
        $this->statusId = $statusId;
        $this->workdirId = $workdirId;
        $this->updatedAt = $this->createdAt();
        $this->enteredAt = $this->createdAt();
    }

    public function id(): IssueId
    {
        return $this->id;
    }

    public function key(): IssueKey
    {
        return $this->key;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function issueTypeId(): IssueTypeId
    {
        return $this->issueTypeId;
    }

    public function statusId(): StatusId
    {
        return $this->statusId;
    }

    public function workdirId(): ?WorkdirId
    {
        return $this->workdirId;
    }

    public function parentKey(): ?IssueKey
    {
        return $this->parentKey;
    }

    public function assignWorkdir(WorkdirId $workdirId): void
    {
        $this->workdirId = $workdirId;
    }

    public function summary(): string
    {
        return $this->summary->getValue();
    }

    public function description(): string
    {
        return $this->description->getValue();
    }

    public function reporter(): string
    {
        return $this->reporter->getValue();
    }

    public function assigneeId(): ?string
    {
        return $this->assigneeId;
    }

    public function worker(): string
    {
        return $this->worker;
    }

    public function blocked(): bool
    {
        return $this->blocked;
    }

    public function fieldValues(): FieldValues
    {
        return $this->fieldValues;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function enteredAt(): DateTimeImmutable
    {
        return $this->enteredAt;
    }

    public function rename(string $summary): void
    {
        $this->summary = new StringObject($this->validSummary($summary));
    }

    public function describe(string $description): void
    {
        $this->description = new StringObject($this->validDescription($description));
    }

    public function assign(?string $assigneeId): void
    {
        $this->assigneeId = $this->validAssignee($assigneeId);
    }

    public function assignWorker(string $worker): void
    {
        $this->worker = trim($worker);
    }

    public function clearWorker(): void
    {
        $this->worker = '';
    }

    public function block(bool $blocked): void
    {
        $this->blocked = $blocked;
    }

    public function changeFieldValues(FieldValues $fieldValues): void
    {
        $this->fieldValues = $fieldValues;
    }

    public function moveTo(StatusId $statusId, DateTimeImmutable $at): void
    {
        $this->statusId = $statusId;
        $this->enteredAt = $at;
        $this->updatedAt = $at;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    public function restoreTimestamps(DateTimeImmutable $updatedAt, DateTimeImmutable $enteredAt): void
    {
        $this->updatedAt = $updatedAt;
        $this->enteredAt = $enteredAt;
    }

    private function validSummary(string $summary): string
    {
        $normalized = trim($summary);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('summary', 'Summary must have 1 to 80 characters.');
        }

        return $normalized;
    }

    private function validDescription(string $description): string
    {
        if (mb_strlen($description) > 4095) {
            throw new ValidationException('description', 'Description must have at most 4095 characters.');
        }

        return $description;
    }

    private function validReporter(string $reporter): string
    {
        $normalized = trim($reporter);
        if ($normalized === '' || mb_strlen($normalized) > 64) {
            throw new ValidationException('reporter', 'Reporter must have 1 to 64 characters.');
        }

        return $normalized;
    }

    private function validAssignee(?string $assigneeId): ?string
    {
        if ($assigneeId === null || trim($assigneeId) === '') {
            return null;
        }
        $normalized = trim($assigneeId);
        if (mb_strlen($normalized) > 64) {
            throw new ValidationException('assigneeId', 'Assignee must have at most 64 characters.');
        }

        return $normalized;
    }
}
