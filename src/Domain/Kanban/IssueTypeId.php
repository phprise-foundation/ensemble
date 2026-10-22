<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use InvalidArgumentException;

final readonly class IssueTypeId
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $value)) {
            throw new InvalidArgumentException('Issue type id must be a 32-character hexadecimal string.');
        }
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    public function value(): string
    {
        return $this->value;
    }
}
