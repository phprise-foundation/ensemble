<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspacePrefix;

final readonly class IssueKey
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^[A-Z][A-Z0-9]{1,9}-[1-9][0-9]*$/', $value)) {
            throw new ValidationException('key', 'Issue key must look like PREFIX-123.');
        }
    }

    public static function from(WorkspacePrefix $prefix, int $number): self
    {
        return new self($prefix->value() . '-' . $number);
    }

    public function value(): string
    {
        return $this->value;
    }
}
