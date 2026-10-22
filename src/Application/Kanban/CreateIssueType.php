<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Level;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateIssueType
{
    public function __construct(
        private WorkflowRepository $workflows,
        private IssueTypeRepository $issueTypes,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
        Slug $key,
        string $name,
        Level $level,
        Position $position,
    ): IssueType {
        $this->guard->assertManager($actor, $workspaceId);
        $this->workflows->find($workspaceId, $workflowId) ?? throw NotFoundException::workflow();
        if ($this->issueTypes->findByKey($workflowId, $key) !== null) {
            throw ConflictException::duplicateKey();
        }

        $issueType = new IssueType(IssueTypeId::generate(), $workspaceId, $workflowId, $key, $name, $level, $position);
        $this->issueTypes->add($issueType);

        return $issueType;
    }
}
