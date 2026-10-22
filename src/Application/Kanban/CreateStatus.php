<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateStatus
{
    public function __construct(
        private WorkflowRepository $workflows,
        private IssueTypeRepository $issueTypes,
        private StatusRepository $statuses,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
        IssueTypeId $issueTypeId,
        Slug $key,
        string $name,
        StatusType $type,
        string $color,
        Position $position,
    ): Status {
        $this->guard->assertManager($actor, $workspaceId);
        $this->workflows->find($workspaceId, $workflowId) ?? throw NotFoundException::workflow();
        $issueType = $this->issueTypes->find($workspaceId, $issueTypeId) ?? throw NotFoundException::issueType();
        if ($issueType->workflowId()->value() !== $workflowId->value()) {
            throw NotFoundException::issueType();
        }
        if ($this->statuses->findByKey($issueTypeId, $key) !== null) {
            throw ConflictException::duplicateKey();
        }
        $this->guardInitial($issueTypeId, $type);

        $status = new Status(
            StatusId::generate(),
            $workspaceId,
            $workflowId,
            $issueTypeId,
            $key,
            $name,
            $type,
            $color,
            $position,
        );
        $this->statuses->add($status);

        return $status;
    }

    private function guardInitial(IssueTypeId $issueTypeId, StatusType $type): void
    {
        $count = $this->statuses->countForIssueType($issueTypeId);
        if ($count === 0 && $type !== StatusType::Initial) {
            throw new ValidationException('type', 'The first status of an issue type must be initial.');
        }
        if ($type === StatusType::Initial && $this->statuses->countByType($issueTypeId, StatusType::Initial) > 0) {
            throw ConflictException::initialStatus();
        }
    }
}
