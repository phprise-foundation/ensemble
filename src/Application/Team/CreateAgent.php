<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\AvatarColor;
use App\Domain\Team\LoopInterval;
use App\Domain\Team\MaritalStatus;
use App\Domain\Team\Portrait;
use App\Domain\Team\ProfileId;
use App\Domain\Team\RoleIds;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateAgent
{
    public function __construct(
        private TeamRepository $teams,
        private AgentRepository $agents,
        private RoleRepository $roles,
        private ProfileRepository $profiles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        TeamId $teamId,
        string $name,
        string $whoAmI,
        ?int $age,
        ?string $maritalStatus,
        RoleIds $roleIds,
        ?ProfileId $profileId,
        ?string $avatarColor,
        ?string $portrait,
        string $model,
        string $fallbackModel,
        ?int $interval,
        bool $active,
    ): Agent {
        $this->guard->assertManager($actor, $workspaceId);
        $this->teams->find($workspaceId, $teamId) ?? throw NotFoundException::team();
        $this->guardRoles($workspaceId, $roleIds);
        $this->guardProfile($workspaceId, $profileId);

        $agent = new Agent(
            AgentId::generate(),
            $workspaceId,
            $teamId,
            $name,
            $whoAmI,
            $age,
            $maritalStatus === null ? MaritalStatus::Single : MaritalStatus::fromString($maritalStatus),
            $roleIds,
            $profileId,
            $avatarColor === null ? new AvatarColor('#4c9aff') : new AvatarColor($avatarColor),
            new Portrait($portrait ?? ''),
            $model,
            $fallbackModel,
            $interval === null ? new LoopInterval(300) : new LoopInterval($interval),
            $active,
        );
        $this->agents->add($agent);

        return $agent;
    }

    private function guardRoles(WorkspaceId $workspaceId, RoleIds $roleIds): void
    {
        if ($this->roles->findMany($workspaceId, $roleIds)->count() !== $roleIds->count()) {
            throw new ValidationException('roleIds', 'Every role must belong to this workspace.');
        }
    }

    private function guardProfile(WorkspaceId $workspaceId, ?ProfileId $profileId): void
    {
        if ($profileId !== null && $this->profiles->find($workspaceId, $profileId) === null) {
            throw new ValidationException('profileId', 'The profile must belong to this workspace.');
        }
    }
}
