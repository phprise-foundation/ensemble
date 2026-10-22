<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

final readonly class FieldValues
{
    /** @var array<string, mixed> */
    private array $values;

    /** @param array<string, mixed> $values */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
