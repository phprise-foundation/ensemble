<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Role
{
    private StringObject $name;
    private StringObject $description;
    private bool $unblock;
    private bool $interactive;
    private bool $implement;
    private bool $review;
    private bool $test;
    private bool $merge;
    private bool $deploy;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly RoleId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly TeamId $teamId,
        string $name,
        string $description = '',
        bool $unblock = false,
        bool $interactive = false,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        bool $implement = false,
        bool $review = false,
        bool $test = false,
        bool $merge = false,
        bool $deploy = false,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->description = new StringObject($this->validDescription($description));
        $this->unblock = $unblock;
        $this->interactive = $interactive;
        $this->implement = $implement;
        $this->review = $review;
        $this->test = $test;
        $this->merge = $merge;
        $this->deploy = $deploy;
        $this->updatedAt = $updatedAt;
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function teamId(): TeamId
    {
        return $this->teamId;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function description(): string
    {
        return $this->description->getValue();
    }

    public function unblock(): bool
    {
        return $this->unblock;
    }

    public function interactive(): bool
    {
        return $this->interactive;
    }

    public function implement(): bool
    {
        return $this->implement;
    }

    public function review(): bool
    {
        return $this->review;
    }

    public function test(): bool
    {
        return $this->test;
    }

    public function merge(): bool
    {
        return $this->merge;
    }

    public function deploy(): bool
    {
        return $this->deploy;
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

    public function describe(string $description): void
    {
        $this->description = new StringObject($this->validDescription($description));
    }

    public function allowUnblock(bool $unblock): void
    {
        $this->unblock = $unblock;
    }

    public function allowInteractive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }

    public function allowImplement(bool $implement): void
    {
        $this->implement = $implement;
    }

    public function allowReview(bool $review): void
    {
        $this->review = $review;
    }

    public function allowTest(bool $test): void
    {
        $this->test = $test;
    }

    public function allowMerge(bool $merge): void
    {
        $this->merge = $merge;
    }

    public function allowDeploy(bool $deploy): void
    {
        $this->deploy = $deploy;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('name', 'Role name must have 1 to 80 characters.');
        }

        return $normalized;
    }

    private function validDescription(string $description): string
    {
        $normalized = trim($description);
        if (mb_strlen($normalized) > 500) {
            throw new ValidationException('description', 'Role description must have at most 500 characters.');
        }

        return $normalized;
    }
}
