<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\FieldDefinition;

final class FieldDefinitionPresenter
{
    /** @return array<string, mixed> */
    public function present(FieldDefinition $field): array
    {
        return [
            'id' => $field->id()->value(),
            'issueTypeId' => $field->issueTypeId()->value(),
            'key' => $field->key()->value(),
            'label' => $field->label(),
            'type' => $field->type()->value,
            'required' => $field->isRequired(),
            'options' => $field->options()->all(),
            'position' => $field->position()->value(),
            'createdAt' => $field->createdAt()->format(DATE_ATOM),
            'updatedAt' => $field->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<FieldDefinition> $fields
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $fields): array
    {
        return array_map(fn(FieldDefinition $field): array => $this->present($field), $fields);
    }
}
