<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;

final readonly class FieldOptions
{
    /** @var list<string> */
    private array $options;

    /** @param list<string> $options */
    public function __construct(array $options)
    {
        $normalized = [];
        foreach ($options as $option) {
            $value = trim($option);
            if ($value === '' || mb_strlen($value) > 80) {
                throw new ValidationException('options', 'Each option must have 1 to 80 characters.');
            }
            if (in_array($value, $normalized, true)) {
                throw new ValidationException('options', 'Options must not repeat.');
            }
            $normalized[] = $value;
        }

        $this->options = $normalized;
    }

    public static function none(): self
    {
        return new self([]);
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->options;
    }

    public function isEmpty(): bool
    {
        return $this->options === [];
    }

    public function contains(string $option): bool
    {
        return in_array($option, $this->options, true);
    }
}
