<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\FieldOptions;
use App\Domain\Kanban\FieldType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateFieldDefinition
{
    public function __construct(
        private IssueTypeRepository $issueTypes,
        private FieldDefinitionRepository $fields,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        IssueTypeId $issueTypeId,
        Slug $key,
        string $label,
        FieldType $type,
        FieldOptions $options,
        bool $required,
        Position $position,
    ): FieldDefinition {
        $this->guard->assertManager($actor, $workspaceId);
        $this->issueTypes->find($workspaceId, $issueTypeId) ?? throw NotFoundException::issueType();
        if ($this->fields->findByKey($issueTypeId, $key) !== null) {
            throw ConflictException::duplicateKey();
        }

        $field = new FieldDefinition(
            FieldDefinitionId::generate(),
            $workspaceId,
            $issueTypeId,
            $key,
            $label,
            $type,
            $options,
            $required,
            $position,
        );
        $this->fields->add($field);

        return $field;
    }
}
