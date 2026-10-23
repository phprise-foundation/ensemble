<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;

final readonly class AvatarColor
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            throw new ValidationException('avatarColor', 'Avatar color must be a hex color such as #4c9aff.');
        }
    }

    public function value(): string
    {
        return strtolower($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }
}
