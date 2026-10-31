<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\Role;
use App\Domain\Team\RoleId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateRole
{
    public function __construct(
        private RoleRepository $roles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        RoleId $id,
        ?string $name,
        ?string $description,
        ?bool $unblock,
        ?bool $interactive,
        ?bool $implement = null,
        ?bool $review = null,
        ?bool $test = null,
        ?bool $merge = null,
        ?bool $deploy = null,
    ): Role {
        $this->guard->assertManager($actor, $workspaceId);
        $role = $this->roles->find($workspaceId, $id) ?? throw NotFoundException::role();
        if ($name !== null) {
            $role->rename($name);
        }
        if ($description !== null) {
            $role->describe($description);
        }
        if ($unblock !== null) {
            $role->allowUnblock($unblock);
        }
        if ($interactive !== null) {
            $role->allowInteractive($interactive);
        }
        if ($implement !== null) {
            $role->allowImplement($implement);
        }
        if ($review !== null) {
            $role->allowReview($review);
        }
        if ($test !== null) {
            $role->allowTest($test);
        }
        if ($merge !== null) {
            $role->allowMerge($merge);
        }
        if ($deploy !== null) {
            $role->allowDeploy($deploy);
        }
        $role->touch(new DateTimeImmutable());
        $this->roles->update($role);

        return $role;
    }
}
