<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roles')]
#[ORM\Index(name: 'idx_roles_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_roles_team', columns: ['team_id'])]
final class RoleRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'team_id', type: 'string', length: 32)]
    private string $teamId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'string', length: 500)]
    private string $description;

    #[ORM\Column(type: 'boolean')]
    private bool $unblock;

    #[ORM\Column(type: 'boolean')]
    private bool $interactive;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $implement;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $review;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $test;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $merge;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $deploy;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $teamId,
        string $name,
        string $description,
        bool $unblock,
        bool $interactive,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        bool $implement = false,
        bool $review = false,
        bool $test = false,
        bool $merge = false,
        bool $deploy = false,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->teamId = $teamId;
        $this->name = $name;
        $this->description = $description;
        $this->unblock = $unblock;
        $this->interactive = $interactive;
        $this->implement = $implement;
        $this->review = $review;
        $this->test = $test;
        $this->merge = $merge;
        $this->deploy = $deploy;
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

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
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
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function sync(
        string $name,
        string $description,
        bool $unblock,
        bool $interactive,
        DateTimeImmutable $updatedAt,
        bool $implement,
        bool $review,
        bool $test,
        bool $merge,
        bool $deploy,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->unblock = $unblock;
        $this->interactive = $interactive;
        $this->updatedAt = $updatedAt;
        $this->implement = $implement;
        $this->review = $review;
        $this->test = $test;
        $this->merge = $merge;
        $this->deploy = $deploy;
    }
}
