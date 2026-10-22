<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class BoardColumn
{
    private StringObject $name;
    private Position $position;
    private ?int $wip;
    private StatusIds $statusIds;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly BoardColumnId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly BoardId $boardId,
        string $name,
        Position $position = new Position(0),
        ?int $wip = null,
        StatusIds $statusIds = new StatusIds(),
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->position = $position;
        $this->wip = $this->validWip($wip);
        $this->statusIds = $statusIds;
        $this->updatedAt = $updatedAt;
    }

    public function id(): BoardColumnId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function boardId(): BoardId
    {
        return $this->boardId;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function wip(): ?int
    {
        return $this->wip;
    }

    public function statusIds(): StatusIds
    {
        return $this->statusIds;
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

    public function placeAt(Position $position): void
    {
        $this->position = $position;
    }

    public function changeWip(?int $wip): void
    {
        $this->wip = $this->validWip($wip);
    }

    public function changeStatuses(StatusIds $statusIds): void
    {
        $this->statusIds = $statusIds;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('name', 'Column name must have 1 to 80 characters.');
        }

        return $normalized;
    }

    private function validWip(?int $wip): ?int
    {
        if ($wip !== null && $wip < 1) {
            throw new ValidationException('wip', 'WIP limit must be at least 1.');
        }

        return $wip;
    }
}
