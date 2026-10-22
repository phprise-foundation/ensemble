<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Exception\ValidationException;

final readonly class WorkspacePrefix
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));
        if (!preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $normalized)) {
            throw new ValidationException('prefix', 'Prefix must have 2 to 10 characters, start with a letter and use only letters and digits.');
        }

        $this->value = $normalized;
    }

    public static function fromName(string $name): self
    {
        $letters = (string) preg_replace('/[^A-Za-z0-9]/', '', $name);
        $candidate = strtoupper(substr($letters, 0, 10));
        if (!preg_match('/^[A-Z]/', $candidate)) {
            $candidate = 'WS' . $candidate;
        }

        return new self(substr($candidate, 0, 10));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
