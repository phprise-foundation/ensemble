<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;

final readonly class Slug
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^[a-z][a-z0-9-]{1,63}$/', $value)) {
            throw new ValidationException('key', 'Key must have 2 to 64 characters, start with a letter and use lowercase letters, digits or dash.');
        }

        $this->value = $value;
    }

    public static function fromName(string $name): self
    {
        $slug = strtolower(trim($name));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'item';
        }
        if (!preg_match('/^[a-z]/', $slug)) {
            $slug = 'item-' . $slug;
        }

        return new self(substr($slug, 0, 64));
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
