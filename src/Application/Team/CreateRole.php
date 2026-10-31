<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\Role;
use App\Domain\Team\RoleId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateRole
{
    public function __construct(
        private TeamRepository $teams,
        private RoleRepository $roles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        TeamId $teamId,
        string $name,
        string $description,
        bool $unblock,
        bool $interactive,
        bool $implement = false,
        bool $review = false,
        bool $test = false,
        bool $merge = false,
        bool $deploy = false,
    ): Role {
        $this->guard->assertManager($actor, $workspaceId);
        $this->teams->find($workspaceId, $teamId) ?? throw NotFoundException::team();

        $role = new Role(
            RoleId::generate(),
            $workspaceId,
            $teamId,
            $name,
            $description,
            $unblock,
            $interactive,
            implement: $implement,
            review: $review,
            test: $test,
            merge: $merge,
            deploy: $deploy,
        );
        $this->roles->add($role);

        return $role;
    }
}
