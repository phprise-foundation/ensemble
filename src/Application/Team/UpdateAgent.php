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
use DateTimeImmutable;

final readonly class UpdateAgent
{
    public function __construct(
        private AgentRepository $agents,
        private TeamRepository $teams,
        private RoleRepository $roles,
        private ProfileRepository $profiles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        AgentId $id,
        ?string $name,
        ?string $whoAmI,
        bool $ageSet,
        ?int $age,
        ?string $maritalStatus,
        ?TeamId $teamId,
        ?RoleIds $roleIds,
        bool $profileIdSet,
        ?ProfileId $profileId,
        ?string $avatarColor,
        ?string $portrait,
        ?string $model,
        ?string $fallbackModel,
        ?int $interval,
        ?bool $active,
    ): Agent {
        $this->guard->assertManager($actor, $workspaceId);
        $agent = $this->agents->find($workspaceId, $id) ?? throw NotFoundException::agent();

        if ($teamId !== null) {
            $this->teams->find($workspaceId, $teamId) ?? throw NotFoundException::team();
            $agent->joins($teamId);
        }
        if ($name !== null || $whoAmI !== null || $ageSet || $maritalStatus !== null) {
            $agent->redefine(
                $name ?? $agent->name(),
                $whoAmI ?? $agent->whoAmI(),
                $ageSet ? $age : $agent->age(),
                $maritalStatus === null ? $agent->maritalStatus() : MaritalStatus::fromString($maritalStatus),
            );
        }
        if ($roleIds !== null) {
            $this->guardRoles($workspaceId, $roleIds);
            $agent->assignRoles($roleIds);
        }
        if ($profileIdSet) {
            $this->guardProfile($workspaceId, $profileId);
            $agent->assignProfile($profileId);
        }

        if ($avatarColor !== null) {
            $agent->colorWith(new AvatarColor($avatarColor));
        }
        if ($portrait !== null) {
            $agent->wear(new Portrait($portrait));
        }
        if ($model !== null || $fallbackModel !== null || $interval !== null) {
            $agent->configure(
                $model ?? $agent->model(),
                $fallbackModel ?? $agent->fallbackModel(),
                $interval === null ? $agent->interval() : new LoopInterval($interval),
            );
        }
        if ($active !== null) {
            $agent->activate($active);
        }

        $agent->touch(new DateTimeImmutable());
        $this->agents->update($agent);

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
