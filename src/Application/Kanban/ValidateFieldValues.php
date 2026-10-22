<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\FieldType;
use App\Domain\Kanban\FieldValues;

final readonly class ValidateFieldValues
{
    /**
     * @param list<FieldDefinition> $definitions
     * @param array<string, mixed> $values
     */
    public function execute(array $definitions, array $values): FieldValues
    {
        $byKey = [];
        foreach ($definitions as $definition) {
            $byKey[$definition->key()->value()] = $definition;
        }
        foreach (array_keys($values) as $key) {
            if (!isset($byKey[$key])) {
                throw new ValidationException('fields.' . $key, 'Unknown field.');
            }
        }

        /** @var array<string, mixed> $normalized */
        $normalized = [];
        foreach ($definitions as $definition) {
            $key = $definition->key()->value();
            if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                if ($definition->isRequired()) {
                    throw new ValidationException('fields.' . $key, 'This field is required.');
                }
                continue;
            }
            $normalized[$key] = $this->normalize($definition, $values[$key]);
        }

        return new FieldValues($normalized);
    }

    /** @return string|int|float|bool|list<string> */
    private function normalize(FieldDefinition $definition, mixed $value): string|int|float|bool|array
    {
        $field = 'fields.' . $definition->key()->value();

        return match ($definition->type()) {
            FieldType::Text => $this->text($value, $field, 255),
            FieldType::Textarea => $this->text($value, $field, 4095),
            FieldType::Number => $this->number($value, $field),
            FieldType::Date => $this->date($value, $field),
            FieldType::Boolean => $this->boolean($value, $field),
            FieldType::SingleSelect => $this->singleSelect($definition, $value, $field),
            FieldType::MultiSelect => $this->multiSelect($definition, $value, $field),
        };
    }

    private function text(mixed $value, string $field, int $limit): string
    {
        if (!is_string($value) || mb_strlen($value) > $limit) {
            throw new ValidationException($field, 'Expected a text with at most ' . $limit . ' characters.');
        }

        return $value;
    }

    private function number(mixed $value, string $field): int|float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new ValidationException($field, 'Expected a number.');
        }

        return $value;
    }

    private function date(mixed $value, string $field): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new ValidationException($field, 'Expected a date in YYYY-MM-DD.');
        }

        return $value;
    }

    private function boolean(mixed $value, string $field): bool
    {
        if (!is_bool($value)) {
            throw new ValidationException($field, 'Expected a boolean.');
        }

        return $value;
    }

    private function singleSelect(FieldDefinition $definition, mixed $value, string $field): string
    {
        if (!is_string($value) || !$definition->options()->contains($value)) {
            throw new ValidationException($field, 'Expected one of the configured options.');
        }

        return $value;
    }

    /** @return list<string> */
    private function multiSelect(FieldDefinition $definition, mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new ValidationException($field, 'Expected a list of configured options.');
        }

        $selected = [];
        foreach ($value as $item) {
            if (!is_string($item) || !$definition->options()->contains($item)) {
                throw new ValidationException($field, 'Expected a list of configured options.');
            }
            if (!in_array($item, $selected, true)) {
                $selected[] = $item;
            }
        }

        return $selected;
    }
}
