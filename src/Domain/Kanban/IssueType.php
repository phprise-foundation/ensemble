<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class IssueType
{
    private StringObject $name;
    private Level $level;
    private Position $position;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly IssueTypeId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly WorkflowId $workflowId,
        private readonly Slug $key,
        string $name,
        Level $level,
        Position $position = new Position(0),
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->level = $level;
        $this->position = $position;
        $this->updatedAt = $updatedAt;
    }

    public function id(): IssueTypeId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function workflowId(): WorkflowId
    {
        return $this->workflowId;
    }

    public function key(): Slug
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function level(): Level
    {
        return $this->level;
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($this->validName($name));
    }

    public function changeLevel(Level $level): void
    {
        $this->level = $level;
    }

    public function placeAt(Position $position): void
    {
        $this->position = $position;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('name', 'Issue type name must have 1 to 80 characters.');
        }

        return $normalized;
    }
}
