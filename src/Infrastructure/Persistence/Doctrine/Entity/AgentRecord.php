<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'agents')]
#[ORM\Index(name: 'idx_agents_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_agents_team', columns: ['team_id'])]
final class AgentRecord
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

    #[ORM\Column(name: 'who_am_i', type: 'string', length: 500)]
    private string $whoAmI;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $age;

    #[ORM\Column(name: 'marital_status', type: 'string', length: 16)]
    private string $maritalStatus;

    #[ORM\Column(name: 'profile_id', type: 'string', length: 32, nullable: true)]
    private ?string $profileId;

    #[ORM\Column(name: 'avatar_color', type: 'string', length: 9)]
    private string $avatarColor;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $portrait;

    #[ORM\Column(type: 'string', length: 120)]
    private string $model;

    #[ORM\Column(name: 'fallback_model', type: 'string', length: 120)]
    private string $fallbackModel;

    #[ORM\Column(name: 'loop_interval', type: 'integer')]
    private int $loopInterval;

    #[ORM\Column(type: 'boolean')]
    private bool $active;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $teamId,
        string $name,
        string $whoAmI,
        ?int $age,
        string $maritalStatus,
        ?string $profileId,
        string $avatarColor,
        ?string $portrait,
        string $model,
        string $fallbackModel,
        int $loopInterval,
        bool $active,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->teamId = $teamId;
        $this->name = $name;
        $this->whoAmI = $whoAmI;
        $this->age = $age;
        $this->maritalStatus = $maritalStatus;
        $this->profileId = $profileId;
        $this->avatarColor = $avatarColor;
        $this->portrait = $portrait;
        $this->model = $model;
        $this->fallbackModel = $fallbackModel;
        $this->loopInterval = $loopInterval;
        $this->active = $active;
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

    public function whoAmI(): string
    {
        return $this->whoAmI;
    }

    public function age(): ?int
    {
        return $this->age;
    }

    public function maritalStatus(): string
    {
        return $this->maritalStatus;
    }

    public function profileId(): ?string
    {
        return $this->profileId;
    }

    public function avatarColor(): string
    {
        return $this->avatarColor;
    }

    public function portrait(): ?string
    {
        return $this->portrait;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function fallbackModel(): string
    {
        return $this->fallbackModel;
    }

    public function loopInterval(): int
    {
        return $this->loopInterval;
    }

    public function active(): bool
    {
        return $this->active;
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
        string $teamId,
        string $name,
        string $whoAmI,
        ?int $age,
        string $maritalStatus,
        ?string $profileId,
        string $avatarColor,
        ?string $portrait,
        string $model,
        string $fallbackModel,
        int $loopInterval,
        bool $active,
        DateTimeImmutable $updatedAt,
    ): void {
        $this->teamId = $teamId;
        $this->name = $name;
        $this->whoAmI = $whoAmI;
        $this->age = $age;
        $this->maritalStatus = $maritalStatus;
        $this->profileId = $profileId;
        $this->avatarColor = $avatarColor;
        $this->portrait = $portrait;
        $this->model = $model;
        $this->fallbackModel = $fallbackModel;
        $this->loopInterval = $loopInterval;
        $this->active = $active;
        $this->updatedAt = $updatedAt;
    }
}
