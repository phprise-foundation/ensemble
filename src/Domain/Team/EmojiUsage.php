<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;

enum EmojiUsage: string
{
    case Never = 'Never';
    case Moderate = 'Moderate';
    case Frequent = 'Frequent';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationException('emojiUsage', 'Emoji usage must be Never, Moderate or Frequent.');
    }
}
