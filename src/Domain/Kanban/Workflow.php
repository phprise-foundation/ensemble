<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Workflow
{
    private StringObject $name;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly WorkflowId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly Slug $key,
        string $name,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->updatedAt = $updatedAt;
    }

    public function id(): WorkflowId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function key(): Slug
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name->getValue();
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

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('name', 'Workflow name must have 1 to 80 characters.');
        }

        return $normalized;
    }
}
