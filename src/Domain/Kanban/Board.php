<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Board
{
    private StringObject $name;
    private BoardFilters $filters;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly BoardId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly WorkflowId $workflowId,
        private readonly Slug $key,
        string $name,
        BoardFilters $filters = new BoardFilters(),
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->filters = $filters;
        $this->updatedAt = $updatedAt;
    }

    public function id(): BoardId
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

    public function filters(): BoardFilters
    {
        return $this->filters;
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

    public function changeFilters(BoardFilters $filters): void
    {
        $this->filters = $filters;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('name', 'Board name must have 1 to 80 characters.');
        }

        return $normalized;
    }
}
