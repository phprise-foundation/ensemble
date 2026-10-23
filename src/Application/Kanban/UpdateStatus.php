<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Team\RoleIds;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateStatus
{
    public function __construct(
        private StatusRepository $statuses,
        private RoleRepository $roles,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        StatusId $id,
        ?string $name,
        ?StatusType $type,
        ?string $color,
        ?Position $position,
        ?RoleIds $roleIds,
    ): Status {
        $this->guard->assertManager($actor, $workspaceId);
        $status = $this->statuses->find($workspaceId, $id) ?? throw NotFoundException::status();
        if ($type !== null) {
            $this->guardType($status, $type);
            $status->changeType($type);
        }
        if ($name !== null) {
            $status->rename($name);
        }
        if ($color !== null) {
            $status->recolor($color);
        }
        if ($position !== null) {
            $status->placeAt($position);
        }
        if ($roleIds !== null) {
            $this->guardRoles($workspaceId, $roleIds);
            $status->assignRoles($roleIds);
        }
        $status->touch(new DateTimeImmutable());
        $this->statuses->update($status);

        return $status;
    }

    private function guardRoles(WorkspaceId $workspaceId, RoleIds $roleIds): void
    {
        if ($this->roles->findMany($workspaceId, $roleIds)->count() !== $roleIds->count()) {
            throw new ValidationException('roleIds', 'Every role must belong to this workspace.');
        }
    }

    private function guardType(Status $status, StatusType $type): void
    {
        if ($status->type() === $type) {
            return;
        }
        if ($type === StatusType::Initial && $this->statuses->countByType($status->issueTypeId(), StatusType::Initial) > 0) {
            throw ConflictException::initialStatus();
        }
        if ($status->isInitial() && $this->statuses->countByType($status->issueTypeId(), StatusType::Initial) <= 1) {
            throw ConflictException::initialStatus();
        }
    }
}
