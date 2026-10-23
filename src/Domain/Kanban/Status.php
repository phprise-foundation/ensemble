<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Team\RoleIds;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Status
{
    private StringObject $name;
    private StatusType $type;
    private string $color;
    private Position $position;
    private RoleIds $roleIds;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly StatusId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly WorkflowId $workflowId,
        private readonly IssueTypeId $issueTypeId,
        private readonly Slug $key,
        string $name,
        StatusType $type,
        string $color,
        Position $position = new Position(0),
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?RoleIds $roleIds = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->type = $type;
        $this->color = $this->validColor($color);
        $this->position = $position;
        $this->roleIds = $roleIds ?? new RoleIds();
        $this->updatedAt = $updatedAt;
    }

    public function id(): StatusId
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

    public function issueTypeId(): IssueTypeId
    {
        return $this->issueTypeId;
    }

    public function key(): Slug
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function type(): StatusType
    {
        return $this->type;
    }

    public function color(): string
    {
        return $this->color;
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function roleIds(): RoleIds
    {
        return $this->roleIds;
    }

    public function assignRoles(RoleIds $roleIds): void
    {
        $this->roleIds = $roleIds;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function isInitial(): bool
    {
        return $this->type === StatusType::Initial;
    }

    public function isFinal(): bool
    {
        return $this->type === StatusType::Final;
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($this->validName($name));
    }

    public function changeType(StatusType $type): void
    {
        $this->type = $type;
    }

    public function recolor(string $color): void
    {
        $this->color = $this->validColor($color);
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
            throw new ValidationException('name', 'Status name must have 1 to 80 characters.');
        }

        return $normalized;
    }

    private function validColor(string $color): string
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new ValidationException('color', 'Status color must be a hexadecimal color such as #57d9a3.');
        }

        return $color;
    }
}
