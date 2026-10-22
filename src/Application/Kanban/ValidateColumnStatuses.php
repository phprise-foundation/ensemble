<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\StatusIds;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ValidateColumnStatuses
{
    public function __construct(private StatusRepository $statuses)
    {
    }

    public function execute(WorkspaceId $workspaceId, WorkflowId $workflowId, StatusIds $statusIds): void
    {
        foreach ($statusIds->all() as $statusId) {
            $status = $this->statuses->find($workspaceId, $statusId);
            if ($status === null || $status->workflowId()->value() !== $workflowId->value()) {
                throw new ValidationException('statusIds', 'Every column status must belong to the board workflow.');
            }
        }
    }
}
