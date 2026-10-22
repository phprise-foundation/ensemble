<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\FieldOptions;
use App\Domain\Kanban\FieldType;
use App\Domain\Kanban\Position;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateFieldDefinition
{
    public function __construct(
        private FieldDefinitionRepository $fields,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        FieldDefinitionId $id,
        ?string $label,
        ?FieldType $type,
        ?FieldOptions $options,
        ?bool $required,
        ?Position $position,
    ): FieldDefinition {
        $this->guard->assertManager($actor, $workspaceId);
        $field = $this->fields->find($workspaceId, $id) ?? throw NotFoundException::field();
        if ($label !== null) {
            $field->rename($label);
        }
        $this->changeType($field, $type, $options);
        if ($required !== null) {
            $field->require($required);
        }
        if ($position !== null) {
            $field->placeAt($position);
        }
        $field->touch(new DateTimeImmutable());
        $this->fields->update($field);

        return $field;
    }

    private function changeType(FieldDefinition $field, ?FieldType $type, ?FieldOptions $options): void
    {
        if ($type === null && $options === null) {
            return;
        }

        $nextType = $type ?? $field->type();
        if ($options !== null && !$nextType->allowsOptions()) {
            throw new ValidationException('options', 'Only select fields accept options.');
        }
        $nextOptions = $nextType->allowsOptions()
            ? ($options ?? $field->options())
            : FieldOptions::none();
        $field->changeType($nextType, $nextOptions);
    }
}
