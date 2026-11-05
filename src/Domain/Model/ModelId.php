<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\ValidationException;

final readonly class ModelId
{
    private const MAXIMUM_LENGTH = 80;

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > self::MAXIMUM_LENGTH) {
            throw new ValidationException('modelId', 'Model id must be between 1 and 80 characters.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $trimmed) !== 1) {
            throw new ValidationException('modelId', 'Model id may only contain lowercase letters, digits, dots, dashes and underscores.');
        }
        $this->value = $trimmed;
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
