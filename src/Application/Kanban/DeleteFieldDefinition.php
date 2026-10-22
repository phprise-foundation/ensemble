<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class DeleteFieldDefinition
{
    public function __construct(
        private FieldDefinitionRepository $fields,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, FieldDefinitionId $id): void
    {
        $this->guard->assertManager($actor, $workspaceId);
        $field = $this->fields->find($workspaceId, $id) ?? throw NotFoundException::field();
        $this->fields->delete($field);
    }
}
