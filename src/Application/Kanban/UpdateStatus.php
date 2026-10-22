<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateStatus
{
    public function __construct(
        private StatusRepository $statuses,
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
        $status->touch(new DateTimeImmutable());
        $this->statuses->update($status);

        return $status;
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
