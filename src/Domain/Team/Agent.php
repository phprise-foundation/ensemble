<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Agent
{
    private StringObject $name;

    private StringObject $whoAmI;

    private StringObject $model;

    private StringObject $fallbackModel;

    private ?int $age;

    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly AgentId $id,
        private readonly WorkspaceId $workspaceId,
        private TeamId $teamId,
        string $name,
        string $whoAmI = '',
        ?int $age = null,
        private MaritalStatus $maritalStatus = MaritalStatus::Single,
        private RoleIds $roleIds = new RoleIds(),
        private ?ProfileId $profileId = null,
        private AvatarColor $avatarColor = new AvatarColor('#4c9aff'),
        private Portrait $portrait = new Portrait(''),
        string $model = '',
        string $fallbackModel = '',
        private LoopInterval $interval = new LoopInterval(300),
        private bool $active = true,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->whoAmI = new StringObject($this->validWhoAmI($whoAmI));
        $this->model = new StringObject($this->validModel($model));
        $this->fallbackModel = new StringObject($this->validModel($fallbackModel));
        $this->age = $this->validAge($age);
        $this->updatedAt = $updatedAt;
    }

    public function id(): AgentId
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

    public function whoAmI(): string
    {
        return $this->whoAmI->getValue();
    }

    public function age(): ?int
    {
        return $this->age;
    }

    public function maritalStatus(): MaritalStatus
    {
        return $this->maritalStatus;
    }

    public function roleIds(): RoleIds
    {
        return $this->roleIds;
    }

    public function profileId(): ?ProfileId
    {
        return $this->profileId;
    }

    public function avatarColor(): AvatarColor
    {
        return $this->avatarColor;
    }

    public function portrait(): Portrait
    {
        return $this->portrait;
    }

    public function model(): string
    {
        return $this->model->getValue();
    }

    public function fallbackModel(): string
    {
        return $this->fallbackModel->getValue();
    }

    public function interval(): LoopInterval
    {
        return $this->interval;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function redefine(string $name, string $whoAmI, ?int $age, MaritalStatus $maritalStatus): void
    {
        $this->name = new StringObject($this->validName($name));
        $this->whoAmI = new StringObject($this->validWhoAmI($whoAmI));
        $this->age = $this->validAge($age);
        $this->maritalStatus = $maritalStatus;
    }

    public function joins(TeamId $teamId): void
    {
        $this->teamId = $teamId;
    }

    public function assignRoles(RoleIds $roleIds): void
    {
        $this->roleIds = $roleIds;
    }

    public function assignProfile(?ProfileId $profileId): void
    {
        $this->profileId = $profileId;
    }

    public function colorWith(AvatarColor $avatarColor): void
    {
        $this->avatarColor = $avatarColor;
    }

    public function wear(Portrait $portrait): void
    {
        $this->portrait = $portrait;
    }

    public function configure(string $model, string $fallbackModel, LoopInterval $interval): void
    {
        $this->model = new StringObject($this->validModel($model));
        $this->fallbackModel = new StringObject($this->validModel($fallbackModel));
        $this->interval = $interval;
    }

    public function activate(bool $active): void
    {
        $this->active = $active;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '' || strlen($trimmed) > 80) {
            throw new ValidationException('name', 'Agent name must be between 1 and 80 characters.');
        }

        return $trimmed;
    }

    private function validWhoAmI(string $whoAmI): string
    {
        if (strlen($whoAmI) > 500) {
            throw new ValidationException('whoAmI', 'Who am I must be at most 500 characters.');
        }

        return $whoAmI;
    }

    private function validModel(string $model): string
    {
        if (strlen($model) > 120) {
            throw new ValidationException('model', 'Model must be at most 120 characters.');
        }

        return $model;
    }

    private function validAge(?int $age): ?int
    {
        if ($age !== null && ($age < 1 || $age > 120)) {
            throw new ValidationException('age', 'Age must be between 1 and 120.');
        }

        return $age;
    }
}
