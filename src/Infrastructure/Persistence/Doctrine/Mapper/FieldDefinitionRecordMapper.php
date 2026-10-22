<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\FieldOptions;
use App\Domain\Kanban\FieldType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\FieldDefinitionRecord;

final class FieldDefinitionRecordMapper
{
    public function toDomain(FieldDefinitionRecord $record): FieldDefinition
    {
        return new FieldDefinition(
            new FieldDefinitionId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new IssueTypeId($record->issueTypeId()),
            new Slug($record->key()),
            $record->label(),
            FieldType::fromString($record->type()),
            new FieldOptions($record->options()),
            $record->isRequired(),
            new Position($record->position()),
            $record->createdAt(),
            $record->updatedAt(),
        );
    }

    public function newRecord(FieldDefinition $field): FieldDefinitionRecord
    {
        return new FieldDefinitionRecord(
            $field->id()->value(),
            $field->workspaceId()->value(),
            $field->issueTypeId()->value(),
            $field->key()->value(),
            $field->label(),
            $field->type()->value,
            $field->isRequired(),
            $field->options()->all(),
            $field->position()->value(),
            $field->createdAt(),
            $field->updatedAt(),
        );
    }

    public function sync(FieldDefinitionRecord $record, FieldDefinition $field): void
    {
        $record->sync(
            $field->label(),
            $field->type()->value,
            $field->isRequired(),
            $field->options()->all(),
            $field->position()->value(),
            $field->updatedAt(),
        );
    }
}
