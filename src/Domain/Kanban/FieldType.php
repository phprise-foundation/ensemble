<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationException('type', 'Unknown field type.');
    }

    public function allowsOptions(): bool
    {
        return $this === self::SingleSelect || $this === self::MultiSelect;
    }
}
